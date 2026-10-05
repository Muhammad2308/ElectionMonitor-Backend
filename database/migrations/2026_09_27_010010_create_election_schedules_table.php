<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M10 — election_schedules
 *
 * Official INEC/SIEC dates. Set ONLY by cybernet_superadmin.
 * One row per state per election type (or one row with state_id = NULL for presidential).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_id')
                  ->constrained('elections')
                  ->cascadeOnDelete();

            // NULL = nationwide (presidential only)
            $table->foreignId('state_id')
                  ->nullable()
                  ->constrained('states')
                  ->cascadeOnDelete();

            $table->string('title');                 // "Kano Governorship Election 2027"

            $table->dateTime('starts_at');           // official polls-open (UTC)
            $table->dateTime('ends_at');             // official polls-close (UTC)
            $table->dateTime('accreditation_starts_at')->nullable();

            $table->enum('status', [
                'scheduled',
                'active',
                'closed',
                'postponed',
                'cancelled'
            ])->default('scheduled');

            $table->dateTime('postponed_from')->nullable(); // original starts_at before latest postponement
            $table->text('status_reason')->nullable();

            $table->foreignId('set_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();

            $table->index(['election_id', 'state_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_schedules');
    }
};
