<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M15 — admin_lga_assignments
 *
 * Maps which LGAs a state_admin is responsible for.
 * Uses composite foreign keys to enforce tenant boundary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_lga_assignments', function (Blueprint $table) {
            $table->id();
            
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();
                  
            $table->foreignId('user_id');      // must be role state_admin
            $table->foreignId('lga_id')->constrained('lgas')->cascadeOnDelete();
            
            $table->foreignId('assigned_by');  // usually state_master_admin
            
            $table->boolean('is_active')->default(true);
            $table->dateTime('assigned_at')->useCurrent();
            
            $table->timestamps();
            
            // Uniqueness and composite foreign keys
            $table->unique(['tenant_id', 'user_id', 'lga_id'], 'uq_admin_lga_tenant');
        });
        
        // Composite foreign keys to users table
        DB::statement("
            ALTER TABLE admin_lga_assignments
            ADD CONSTRAINT fk_admin_lga_user
            FOREIGN KEY (tenant_id, user_id) REFERENCES users(tenant_id, id)
            ON DELETE CASCADE
        ");
        
        DB::statement("
            ALTER TABLE admin_lga_assignments
            ADD CONSTRAINT fk_admin_lga_assigned_by
            FOREIGN KEY (tenant_id, assigned_by) REFERENCES users(tenant_id, id)
            ON DELETE CASCADE
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_lga_assignments');
    }
};
