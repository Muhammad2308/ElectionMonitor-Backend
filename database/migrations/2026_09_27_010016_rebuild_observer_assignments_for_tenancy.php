<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M16 — rebuild_observer_assignments_for_tenancy
 *
 * Updates existing observer_assignments to include tenant_id and composite FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns
        Schema::table('observer_assignments', function (Blueprint $table) {
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->restrictOnDelete();

            // Rename user_id to observer_id for clarity
            $table->renameColumn('user_id', 'observer_id');

            // RESTRICT: an election schedule must be explicitly retired/replaced,
            // never deleted out from under assignments that reference it.
            $table->foreignId('election_schedule_id')->nullable()->after('polling_unit_id')->constrained('election_schedules')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('election_schedule_id');

            $table->enum('status', ['active', 'recalled', 'completed'])->default('active')->after('assigned_by');
            $table->dateTime('assigned_at')->useCurrent()->after('status');
        });

        // 2. Backfill tenant_id from the user's tenant_id
        DB::statement("
            UPDATE observer_assignments oa
            JOIN users u ON oa.observer_id = u.id
            SET oa.tenant_id = u.tenant_id,
                oa.assigned_by = u.supervisor_id
            WHERE u.tenant_id IS NOT NULL
        ");

        // 3. Enforce constraints
        Schema::table('observer_assignments', function (Blueprint $table) {
            // Drop old simple foreign key (using explicit name from before column was renamed)
            $table->dropForeign('observer_assignments_user_id_foreign');
        });

        // MySQL/MariaDB treat NULL as distinct in unique indexes, so a NULL
        // election_schedule_id would let the same observer be double-booked
        // to the same polling unit outside a scheduled election. Index a
        // generated column instead, matching the pattern used elsewhere
        // (tenants.state_key, tenant_operational_schedules.state_key).
        Schema::table('observer_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_key')->virtualAs('COALESCE(election_schedule_id, 0)');
            $table->unique(['tenant_id', 'observer_id', 'polling_unit_id', 'schedule_key'], 'uq_obs_assign_tenant');
        });

        DB::statement("
            ALTER TABLE observer_assignments
            ADD CONSTRAINT fk_obs_assign_observer
            FOREIGN KEY (tenant_id, observer_id) REFERENCES users(tenant_id, id)
            ON DELETE CASCADE
        ");

        // RESTRICT, not SET NULL: SET NULL on this composite FK would also
        // null out tenant_id (the other half of the key), silently knocking
        // the row out of its tenant. Whoever made the assignment must be
        // reassigned/handled explicitly before their user row can be deleted.
        DB::statement("
            ALTER TABLE observer_assignments
            ADD CONSTRAINT fk_obs_assign_assigned_by
            FOREIGN KEY (tenant_id, assigned_by) REFERENCES users(tenant_id, id)
            ON DELETE RESTRICT
        ");
    }

    public function down(): void
    {
        Schema::table('observer_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_obs_assign_assigned_by');
            $table->dropForeign('fk_obs_assign_observer');
            $table->dropUnique('uq_obs_assign_tenant');
            $table->dropColumn('schedule_key');

            // Drop the FK on election_schedule_id before dropping the column —
            // MySQL refuses to drop a column while a foreign key still depends on it.
            $table->dropForeign(['election_schedule_id']);
            $table->dropColumn(['status', 'assigned_at', 'assigned_by', 'election_schedule_id']);

            $table->renameColumn('observer_id', 'user_id');

            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');

            // Re-add old foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
