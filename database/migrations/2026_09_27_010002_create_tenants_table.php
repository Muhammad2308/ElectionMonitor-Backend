<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M02 — tenants
 *
 * One row per organisation-deployment.
 * A political party deploying in three states has three tenants.
 * Two parties deploying in the same state have two separate tenants for that state.
 * All tenant-owned data carries tenant_id NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();  // used in storage paths and broadcast channels

            // RESTRICT, not CASCADE: deleting an organisation must never
            // silently take its tenants (and everything those tenants own)
            // with it. Tenant removal only ever happens through the
            // tenant:purge command, after export + confirmation + the
            // 30-day window.
            $table->foreignId('organisation_id')
                  ->constrained('organisations')
                  ->restrictOnDelete();

            $table->enum('scope', ['state', 'national']);

            // Required for scope = state; NULL for national
            $table->foreignId('state_id')
                  ->nullable()
                  ->constrained('states')
                  ->nullOnDelete();

            $table->string('name');                     // "APC · Kano"
            $table->string('slug', 100)->unique();      // "apc-kano"
            $table->string('code', 20)->unique();       // "APC-KN" or "LP-NG"

            $table->enum('status', [
                'pending_payment',
                'active',
                'suspended',
                'read_only',
                'exported',
                'purged',
            ])->default('pending_payment');

            $table->unsignedInteger('max_admins')->nullable();    // licensed cap; NULL = no cap
            $table->unsignedInteger('max_observers')->nullable(); // licensed cap; NULL = no cap

            $table->dateTime('engagement_starts_at')->nullable();
            $table->dateTime('engagement_ends_at')->nullable();
            $table->dateTime('data_handed_over_at')->nullable();
            $table->dateTime('purge_due_at')->nullable();         // handed_over_at + 30 days
            $table->dateTime('purged_at')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // One deployment per organisation per state; one national deployment per organisation
            // Uses a generated column approach via a DB statement below
        });

        // UNIQUE: one deployment per org per (state or national)
        // MySQL does not allow NULL in a standard unique index, so we use
        // a unique index on (organisation_id, scope, state_id) with a partial workaround:
        // two separate unique constraints — one for state scope, one for national scope.
        DB::statement("
            ALTER TABLE tenants
            ADD CONSTRAINT chk_tenants_scope_state_id
            CHECK (
                (scope = 'state') = (state_id IS NOT NULL)
            )
        ");

        // Prevent duplicate (org + state) and (org + national).
        // Since MySQL/MariaDB don't support partial unique indexes (WHERE clause),
        // we use a generated column to represent state_id or 0 if national.
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('state_key')->virtualAs('COALESCE(state_id, 0)');
            $table->unique(['organisation_id', 'scope', 'state_key'], 'uq_tenants_org_scope_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
