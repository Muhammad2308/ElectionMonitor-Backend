<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M07 — support_access_grants
 *
 * "Break-glass" audited access for Cybernet staff to read tenant content.
 * By default cybernet_superadmin cannot open incidents, media or observer details.
 * A tenant master admin can grant time-limited access (max 72 hours).
 * Every use of the grant is recorded in audit_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $table) {
            $table->id();

            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();

            // The master admin of this tenant who authorised the access
            $table->foreignId('granted_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // The Cybernet superadmin who receives the access
            $table->foreignId('granted_to')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->text('reason');                         // mandatory justification
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');                 // max 72 hours from starts_at
            $table->dateTime('revoked_at')->nullable();     // early revocation

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_grants');
    }
};
