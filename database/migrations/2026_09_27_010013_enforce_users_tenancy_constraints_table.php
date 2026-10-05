<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M13 — enforce_users_tenancy_constraints
 *
 * Applies NOT NULL, UNIQUE, and CHECK constraints to the `users` table
 * now that backfilling is complete.
 */
return new class extends Migration
{
    private const ROLE_TYPE_ENUM = "ENUM('cybernet_superadmin','national_master_admin','state_master_admin','state_admin','observer')";

    public function up(): void
    {
        // 1. Drop the old global unique email index
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        // 2. Add new constraints
        Schema::table('users', function (Blueprint $table) {
            // Make email unique per tenant (null tenant = platform users)
            // MySQL allows multiple NULLs in unique indexes, so superadmins can exist
            // but we usually only have one or a few.
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');

            // Target for composite foreign keys from child tables
            $table->unique(['tenant_id', 'id'], 'users_tenant_id_unique');

            // Unique NIN per tenant
            $table->unique(['tenant_id', 'nin_hash'], 'users_tenant_nin_unique');
        });

        // M12 backfilled role_type for every existing row (or aborted if it
        // couldn't), so it's now safe to require it going forward. Raw SQL:
        // doctrine/dbal (needed for Schema::table()->change()) isn't installed.
        DB::statement('ALTER TABLE users MODIFY role_type ' . self::ROLE_TYPE_ENUM . ' NOT NULL');

        // 3. Add CHECK constraints for role/tenant consistency.
        // role_type IS NOT NULL is required explicitly: without it, a NULL
        // role_type makes `role_type != 'cybernet_superadmin'` evaluate to
        // NULL (neither true nor false), which MySQL/MariaDB CHECK treats as
        // satisfied — so a row with role_type/tenant_id/organisation_id all
        // NULL would silently pass.
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT chk_users_superadmin_tenant
            CHECK (
                role_type IS NOT NULL
                AND (
                    (role_type = 'cybernet_superadmin' AND tenant_id IS NULL AND organisation_id IS NULL)
                    OR
                    (role_type != 'cybernet_superadmin' AND tenant_id IS NOT NULL AND organisation_id IS NOT NULL)
                )
            )
        ");

        // 4. Enforce self-referencing supervisor is in the same tenant
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT fk_users_supervisor_tenant
            FOREIGN KEY (tenant_id, supervisor_id) REFERENCES users(tenant_id, id)
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_users_supervisor_tenant');
        });

        DB::statement("ALTER TABLE users DROP CONSTRAINT chk_users_superadmin_tenant");

        DB::statement('ALTER TABLE users MODIFY role_type ' . self::ROLE_TYPE_ENUM . ' NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_email_unique');
            $table->dropUnique('users_tenant_id_unique');
            $table->dropUnique('users_tenant_nin_unique');

            $table->unique('email', 'users_email_unique');
        });
    }
};
