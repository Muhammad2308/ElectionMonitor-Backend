<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M05 — tenant_election_types
 *
 * Records which election types each tenant is entitled to monitor.
 * A state deployment typically excludes 'presidential'.
 * Only tenants with a 'presidential' row receive presidential notifications/countdowns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_election_types', function (Blueprint $table) {
            $table->id();

            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();

            $table->enum('election_type', [
                'presidential',
                'governorship',
                'senate',
                'house_of_reps',
                'state_assembly',
                'chairmanship',
                'councillor',
            ]);

            $table->timestamps();

            $table->unique(['tenant_id', 'election_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_election_types');
    }
};
