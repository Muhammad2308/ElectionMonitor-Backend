<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M10b — election_schedule_amendments
 *
 * Granular postponements and rescheduling at state, LGA, ward, or PU level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_schedule_amendments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('election_schedule_id')
                  ->constrained('election_schedules')
                  ->cascadeOnDelete();

            $table->enum('scope_level', ['state', 'lga', 'ward', 'polling_unit']);

            $table->foreignId('lga_id')->nullable()->constrained('lgas')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->cascadeOnDelete();
            $table->foreignId('polling_unit_id')->nullable()->constrained('polling_units')->cascadeOnDelete();

            $table->enum('amendment_type', [
                'postponement',
                'reschedule',
                'cancellation',
                'restoration',
                'time_change'
            ]);

            $table->dateTime('previous_starts_at')->nullable();
            $table->dateTime('previous_ends_at')->nullable();
            $table->string('previous_status', 30)->nullable();

            $table->dateTime('new_starts_at')->nullable(); // NULL if cancellation
            $table->dateTime('new_ends_at')->nullable();
            $table->enum('new_status', ['scheduled', 'postponed', 'cancelled']);

            $table->text('reason'); // mandatory
            $table->string('authority_ref', 100)->nullable(); // INEC press release ref

            $table->boolean('is_active')->default(true);
            $table->dateTime('effective_from')->useCurrent();

            $table->foreignId('set_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['election_schedule_id', 'scope_level'], 'idx_esa_schedule_scope');
            $table->index(['election_schedule_id', 'lga_id'], 'idx_esa_schedule_lga');
            $table->index(['election_schedule_id', 'polling_unit_id'], 'idx_esa_schedule_pu');
        });

        // MySQL 8 CHECK constraints to ensure scope rules
        DB::statement("
            ALTER TABLE election_schedule_amendments
            ADD CONSTRAINT chk_esa_scope_state
            CHECK (
                scope_level != 'state' OR (lga_id IS NULL AND ward_id IS NULL AND polling_unit_id IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE election_schedule_amendments
            ADD CONSTRAINT chk_esa_scope_lga
            CHECK (
                scope_level != 'lga' OR (lga_id IS NOT NULL AND ward_id IS NULL AND polling_unit_id IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE election_schedule_amendments
            ADD CONSTRAINT chk_esa_scope_ward
            CHECK (
                scope_level != 'ward' OR (lga_id IS NOT NULL AND ward_id IS NOT NULL AND polling_unit_id IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE election_schedule_amendments
            ADD CONSTRAINT chk_esa_scope_pu
            CHECK (
                scope_level != 'polling_unit' OR (polling_unit_id IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('election_schedule_amendments');
    }
};
