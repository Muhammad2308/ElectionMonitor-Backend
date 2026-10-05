<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M04 — tenant_licences
 *
 * Uniform licence fee per deployment (₦8,000,000 standard state rate).
 * The fee is copied from platform_settings at creation time so the record
 * is a historical fact even if the platform rate changes later.
 * A tenant may only become 'active' when a 'paid' licence covers today's date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_licences', function (Blueprint $table) {
            $table->id();

            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();

            $table->string('election_period', 100);      // "2027 General Elections"

            $table->enum('fee_basis', ['standard_state', 'national_quote']);
            $table->decimal('licence_fee', 14, 2);       // copied from platform_settings at creation
            $table->char('currency', 3)->default('NGN');

            $table->string('invoice_ref', 100)->nullable();
            $table->enum('status', ['pending', 'paid', 'cancelled', 'expired'])->default('pending');
            $table->dateTime('paid_at')->nullable();

            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_licences');
    }
};
