<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M06 — platform_settings
 *
 * Simple key/value config store for platform-level settings.
 * Key setting: standard_state_licence_fee = 8000000.00 (NGN)
 * All mutations are audited (updated_by tracks who changed what).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();     // "standard_state_licence_fee"
            $table->text('value');                     // "8000000.00"
            $table->string('description')->nullable();

            $table->foreignId('updated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('updated_at')->nullable();
        });

        // Seed the standard licence fee
        DB::table('platform_settings')->insert([
            'key'         => 'standard_state_licence_fee',
            'value'       => '8000000.00',
            'description' => 'Standard per-state software licence fee in NGN. All parties pay this rate.',
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
