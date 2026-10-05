<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M23 — audit_logs
 *
 * Append-only trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->nullable() // NULL = platform action
                  ->constrained('tenants')
                  ->restrictOnDelete();
                  
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_code', 30)->nullable();
            
            $table->string('action', 100);
            
            $table->string('subject_type', 100)->nullable();
            // string, not bigInteger: subjects include incidents, whose id is
            // a UUID, alongside bigint-keyed entities (users, tenants, etc).
            $table->string('subject_id', 36)->nullable();
            
            $table->json('changes')->nullable();
            
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
