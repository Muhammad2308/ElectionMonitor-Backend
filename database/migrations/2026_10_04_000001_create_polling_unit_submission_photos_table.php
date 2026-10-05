<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photo evidence captured at the polling point. Files live on the private
 * disk and are only served through an authorised endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Target for the composite FK below, so a photo can only point at a
        // submission in its own tenant.
        DB::statement('ALTER TABLE polling_unit_submissions ADD UNIQUE KEY uq_pu_sub_tenant_id (tenant_id, id)');

        Schema::create('polling_unit_submission_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('submission_id');
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->index('submission_id');
        });

        DB::statement("
            ALTER TABLE polling_unit_submission_photos
            ADD CONSTRAINT fk_pu_sub_photo_submission
            FOREIGN KEY (tenant_id, submission_id) REFERENCES polling_unit_submissions(tenant_id, id)
            ON DELETE RESTRICT
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_unit_submission_photos');
        DB::statement('ALTER TABLE polling_unit_submissions DROP INDEX uq_pu_sub_tenant_id');
    }
};
