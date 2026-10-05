<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M09 — elections
 *
 * Catalogue of election types (shared reference — no tenant_id).
 * The 'scope' column is computed: presidential = national, all others = state.
 * Seeded with 7 standard Nigerian election types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // "Governorship Election"
            $table->enum('type', [
                'presidential',
                'governorship',
                'senate',
                'house_of_reps',
                'state_assembly',
                'chairmanship',
                'councillor',
            ])->unique();

            // Derived: presidential → national, all others → state
            // Stored as a plain column; application sets it on create.
            $table->enum('scope', ['national', 'state']);

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the seven election types
        $now = now();
        DB::table('elections')->insert([
            ['name' => 'Presidential Election',         'type' => 'presidential',   'scope' => 'national', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Governorship Election',         'type' => 'governorship',   'scope' => 'state',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Senatorial Election',           'type' => 'senate',         'scope' => 'state',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'House of Representatives',      'type' => 'house_of_reps',  'scope' => 'state',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'State House of Assembly',       'type' => 'state_assembly', 'scope' => 'state',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'LGA Chairmanship Election',     'type' => 'chairmanship',   'scope' => 'state',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Councillorship Election',       'type' => 'councillor',     'scope' => 'state',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('elections');
    }
};
