<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M17 — tenant_operational_schedules
 *
 * Party-specific deployment times. While cybernet sets the OFFICIAL election time,
 * the tenant's state_master_admin sets when THEIR observers must deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_operational_schedules', function (Blueprint $table) {
            $table->id();
            
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();

            // RESTRICT: an election schedule must be explicitly retired/replaced,
            // never deleted out from under schedules that reference it.
            $table->foreignId('election_schedule_id')
                  ->constrained('election_schedules')
                  ->restrictOnDelete();
                  
            $table->foreignId('state_id')
                  ->nullable()
                  ->constrained('states')
                  ->cascadeOnDelete();
                  
            $table->dateTime('deploy_at');               // UTC; "DEPLOY NOW" alert time
            $table->dateTime('first_report_due_at')->nullable();
            
            $table->text('briefing_notes')->nullable();
            
            $table->foreignId('set_by');
            
            $table->timestamps();
            
            // MySQL unique indexes treat NULLs as distinct. To enforce one operational 
            // schedule per election schedule per tenant (where state might be null), 
            // we create a generated column for the constraint.
            $table->unsignedBigInteger('state_key')->virtualAs('COALESCE(state_id, 0)');
            $table->unique(['tenant_id', 'election_schedule_id', 'state_key'], 'uq_tenant_op_schedule');
        });
        
        DB::statement("
            ALTER TABLE tenant_operational_schedules
            ADD CONSTRAINT fk_tenant_op_sched_user
            FOREIGN KEY (tenant_id, set_by) REFERENCES users(tenant_id, id)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_operational_schedules');
    }
};
