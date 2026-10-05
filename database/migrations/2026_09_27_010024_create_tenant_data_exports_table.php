<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M24 — tenant_data_exports
 *
 * Export and handover lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_data_exports', function (Blueprint $table) {
            $table->id();
            
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();
                  
            $table->foreignId('requested_by'); // Usually cybernet_superadmin or national_master_admin
            
            $table->enum('status', [
                'queued',
                'building',
                'ready',
                'handed_over',
                'confirmed',
                'failed'
            ])->default('queued');
            
            $table->char('archive_checksum', 64)->nullable();
            $table->bigInteger('archive_size_bytes')->nullable();
            
            $table->string('handed_over_to')->nullable(); // Client's authorised rep
            $table->dateTime('handed_over_at')->nullable();
            
            $table->dateTime('confirmed_at')->nullable();
            
            $table->timestamps();
        });
        
        DB::statement("
            ALTER TABLE tenant_data_exports
            ADD CONSTRAINT fk_tenant_exports_user
            FOREIGN KEY (tenant_id, requested_by) REFERENCES users(tenant_id, id)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_data_exports');
    }
};
