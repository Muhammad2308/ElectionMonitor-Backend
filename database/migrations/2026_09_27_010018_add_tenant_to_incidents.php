<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M18 — add_tenant_to_incidents
 *
 * Adds multi-tenancy isolation to incidents.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add tenant_id (nullable initially)
        Schema::table('incidents', function (Blueprint $table) {
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->restrictOnDelete();

            $table->renameColumn('user_id', 'reporter_id');
            // RESTRICT: an election schedule must be explicitly retired/replaced,
            // never deleted out from under incidents that reference it.
            $table->foreignId('election_schedule_id')->nullable()->after('polling_unit_id')->constrained('election_schedules')->restrictOnDelete();
            
            $table->decimal('location_accuracy_m', 8, 2)->nullable()->after('longitude');
            $table->dateTime('captured_at')->nullable()->after('location_accuracy_m');
            $table->dateTime('synced_at')->nullable()->after('captured_at');
            
            $table->enum('verification_status', ['unverified', 'verified', 'rejected'])->default('unverified')->after('synced_at');
            
            // Note: status, severity, category, description, lat, lng, created_at, updated_at, softDeletes already exist
            $table->foreignId('reviewed_by')->nullable()->after('status');
        });

        // Backfill tenant_id from reporter's tenant
        DB::statement("
            UPDATE incidents i
            JOIN users u ON i.reporter_id = u.id
            SET i.tenant_id = u.tenant_id
            WHERE u.tenant_id IS NOT NULL
        ");

        // Enforce constraints
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign('incidents_user_id_foreign');
            
            $table->unique(['tenant_id', 'id'], 'uq_incidents_tenant_id');
            $table->index(['tenant_id', 'status', 'severity'], 'idx_incidents_tenant_status_sev');
            $table->index(['tenant_id', 'polling_unit_id'], 'idx_incidents_tenant_pu');
        });

        DB::statement("
            ALTER TABLE incidents
            ADD CONSTRAINT fk_incidents_reporter
            FOREIGN KEY (tenant_id, reporter_id) REFERENCES users(tenant_id, id)
        ");

        DB::statement("
            ALTER TABLE incidents
            ADD CONSTRAINT fk_incidents_reviewer
            FOREIGN KEY (tenant_id, reviewed_by) REFERENCES users(tenant_id, id)
        ");
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign('fk_incidents_reviewer');
            $table->dropForeign('fk_incidents_reporter');
            
            $table->dropIndex('idx_incidents_tenant_pu');
            $table->dropIndex('idx_incidents_tenant_status_sev');
            $table->dropUnique('uq_incidents_tenant_id');

            // Drop the FKs on election_schedule_id/tenant_id before dropping
            // those columns — MySQL refuses to drop a column while a foreign
            // key still depends on it.
            $table->dropForeign(['election_schedule_id']);
            $table->dropForeign(['tenant_id']);

            $table->dropColumn([
                'reviewed_by',
                'verification_status',
                'synced_at',
                'captured_at',
                'location_accuracy_m',
                'election_schedule_id',
                'tenant_id'
            ]);
            
            $table->renameColumn('reporter_id', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
