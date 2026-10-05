<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M01 — organisations
 *
 * Stores every organisation (political party or neutral body) that buys
 * a deployment.  This is a platform-level table; it has no tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name');                          // "All Progressives Congress"
            $table->string('short_code', 10)->unique();      // "APC" — used in tenant codes and user codes
            $table->enum('type', ['political_party', 'neutral']);
            $table->enum('neutral_category', [
                'civic_organisation',
                'government',
                'individual_sponsor',
                'other',
            ])->nullable();                                  // required when type = neutral, NULL otherwise

            $table->string('registration_ref', 100)->nullable();  // INEC party reg / CAC number
            $table->string('logo_path')->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();

            $table->enum('status', ['active', 'suspended'])->default('active');

            // cybernet_superadmin who registered this organisation
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
        });

        // MySQL 8 CHECK: neutral_category must be set iff type = 'neutral'
        DB::statement("
            ALTER TABLE organisations
            ADD CONSTRAINT chk_organisations_neutral_category
            CHECK (
                (type = 'neutral') = (neutral_category IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
