<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces tenant_id NOT NULL on incidents, check_ins, and observer_assignments
 * now that M16/M18/M20 have backfilled every row from its owning user's
 * tenant_id. Mirrors the expand -> backfill -> enforce pattern already used
 * for users in M11/M12/M13.
 *
 * MariaDB/MySQL (error 1832) refuse to MODIFY a column that's part of any
 * foreign key, composite or not — every FK touching tenant_id on these three
 * tables has to be dropped first and re-added afterwards with its original
 * definition.
 *
 * Raw SQL rather than Schema::table(...)->change(): doctrine/dbal isn't a
 * dependency of this project.
 */
return new class extends Migration
{
    private const TABLES = ['incidents', 'check_ins', 'observer_assignments'];

    public function up(): void
    {
        $unresolved = [];
        foreach (self::TABLES as $table) {
            $nullCount = DB::table($table)->whereNull('tenant_id')->count();
            if ($nullCount > 0) {
                $unresolved[] = "{$table} ({$nullCount} row(s))";
            }
        }

        if ($unresolved !== []) {
            throw new \RuntimeException(
                'Cannot enforce tenant_id NOT NULL: unresolved NULL tenant_id rows in ' .
                implode(', ', $unresolved) .
                '. Their owning user likely has no tenant_id either — backfill manually before re-running this migration.'
            );
        }

        $this->dropTenantForeignKeys();

        DB::statement('ALTER TABLE incidents MODIFY tenant_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE check_ins MODIFY tenant_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE observer_assignments MODIFY tenant_id BIGINT UNSIGNED NOT NULL');

        $this->restoreTenantForeignKeys();
    }

    public function down(): void
    {
        $this->dropTenantForeignKeys();

        DB::statement('ALTER TABLE incidents MODIFY tenant_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE check_ins MODIFY tenant_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE observer_assignments MODIFY tenant_id BIGINT UNSIGNED NULL');

        $this->restoreTenantForeignKeys();
    }

    private function dropTenantForeignKeys(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign('incidents_tenant_id_foreign');
        });
        DB::statement('ALTER TABLE incidents DROP FOREIGN KEY fk_incidents_reporter');
        DB::statement('ALTER TABLE incidents DROP FOREIGN KEY fk_incidents_reviewer');

        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropForeign('check_ins_tenant_id_foreign');
        });
        DB::statement('ALTER TABLE check_ins DROP FOREIGN KEY fk_check_ins_observer');

        Schema::table('observer_assignments', function (Blueprint $table) {
            $table->dropForeign('observer_assignments_tenant_id_foreign');
        });
        DB::statement('ALTER TABLE observer_assignments DROP FOREIGN KEY fk_obs_assign_observer');
        DB::statement('ALTER TABLE observer_assignments DROP FOREIGN KEY fk_obs_assign_assigned_by');
    }

    private function restoreTenantForeignKeys(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE incidents ADD CONSTRAINT fk_incidents_reporter FOREIGN KEY (tenant_id, reporter_id) REFERENCES users(tenant_id, id)');
        DB::statement('ALTER TABLE incidents ADD CONSTRAINT fk_incidents_reviewer FOREIGN KEY (tenant_id, reviewed_by) REFERENCES users(tenant_id, id)');

        Schema::table('check_ins', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE check_ins ADD CONSTRAINT fk_check_ins_observer FOREIGN KEY (tenant_id, observer_id) REFERENCES users(tenant_id, id) ON DELETE CASCADE');

        Schema::table('observer_assignments', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE observer_assignments ADD CONSTRAINT fk_obs_assign_observer FOREIGN KEY (tenant_id, observer_id) REFERENCES users(tenant_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE observer_assignments ADD CONSTRAINT fk_obs_assign_assigned_by FOREIGN KEY (tenant_id, assigned_by) REFERENCES users(tenant_id, id) ON DELETE RESTRICT');
    }
};
