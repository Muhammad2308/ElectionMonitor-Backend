<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M22 — user_notifications
 *
 * Per-user inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();
                  
            $table->foreignId('user_id');
            
            $table->foreignId('tenant_notification_id')
                  ->nullable()
                  ->constrained('tenant_notifications')
                  ->cascadeOnDelete();
                  
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable(); // starts_at, deploy_at, polling_unit, etc.
            
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            
            $table->dateTime('read_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'user_id', 'read_at'], 'idx_user_notifications_read');
        });
        
        DB::statement("
            ALTER TABLE user_notifications
            ADD CONSTRAINT fk_user_notif_user
            FOREIGN KEY (tenant_id, user_id) REFERENCES users(tenant_id, id)
            ON DELETE CASCADE
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
