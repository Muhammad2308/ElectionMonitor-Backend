<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M21 — tenant_notifications
 *
 * Scheduled broadcast notification queue. Replaces the generic notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_notifications', function (Blueprint $table) {
            $table->id();
            
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();

            // RESTRICT: an election schedule must be explicitly retired/replaced,
            // never deleted out from under notifications that reference it.
            $table->foreignId('election_schedule_id')
                  ->nullable()
                  ->constrained('election_schedules')
                  ->restrictOnDelete();
                  
            $table->enum('notification_type', [
                'election_scheduled',
                'schedule_changed',
                'reminder_24h',
                'reminder_1h',
                'deploy_now',
                'polls_open',
                'polls_closed',
                'custom'
            ]);
            
            $table->enum('recipient_role', ['all', 'state_admins', 'observers'])->default('all');
            
            $table->foreignId('state_id')
                  ->nullable()
                  ->constrained('states')
                  ->cascadeOnDelete();
                  
            $table->string('title');
            $table->text('body');
            
            $table->dateTime('scheduled_for'); // UTC
            
            $table->enum('status', ['pending', 'sending', 'sent', 'cancelled', 'failed'])->default('pending');
            $table->dateTime('sent_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notifications');
    }
};
