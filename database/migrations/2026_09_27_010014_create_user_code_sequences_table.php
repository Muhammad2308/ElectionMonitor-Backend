<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M14 — user_code_sequences
 *
 * Sequence counters to generate readable, collision-free user codes
 * per tenant and role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_code_sequences', function (Blueprint $table) {
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();
                  
            $table->string('scope_key', 20); // "OB", "SA", "KD-SM"
            
            $table->unsignedInteger('next_value')->default(1);
            
            $table->primary(['tenant_id', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_code_sequences');
    }
};
