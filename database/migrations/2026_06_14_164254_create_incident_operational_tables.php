<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incident_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('observer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('polling_unit_id')->constrained()->onDelete('cascade');
            $table->date('election_date')->index();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('polling_unit_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('incident_categories')->onDelete('cascade');
            $table->text('description');
            $table->dateTime('incident_time')->index();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'conflict'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('incident_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('incident_id')->constrained('incidents')->onDelete('cascade');
            $table->enum('media_type', ['image', 'audio', 'video'])->default('image');
            $table->string('file_path');
            $table->string('file_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_media');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('observer_assignments');
        Schema::dropIfExists('incident_categories');
    }
};
