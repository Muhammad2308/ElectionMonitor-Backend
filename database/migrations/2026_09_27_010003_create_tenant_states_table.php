<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M03 — tenant_states
 *
 * Maps which states a tenant covers.
 * State tenant: one row (its own state).
 * National tenant: one row per covered state.
 * All state-scoped queries (e.g. "which tenants are affected by Kano elections?")
 * go through this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_states', function (Blueprint $table) {
            $table->id();

            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();

            $table->foreignId('state_id')
                  ->constrained('states')
                  ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'state_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_states');
    }
};
