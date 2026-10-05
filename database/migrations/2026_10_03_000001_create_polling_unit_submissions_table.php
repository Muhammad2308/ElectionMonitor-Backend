<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Field-captured polling unit locations awaiting admin review.
 *
 * An observer at a physical polling unit submits either:
 *  - coordinates:       GPS fix for a polling unit already on the official list
 *  - new_polling_unit:  a polling unit missing from the list (ward + name)
 * Nothing touches polling_units until a tenant reviewer approves the submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_unit_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // RESTRICT: tenant removal only happens via tenant:purge.
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();

            $table->foreignId('submitted_by');
            $table->enum('submission_type', ['coordinates', 'new_polling_unit']);

            $table->foreignId('polling_unit_id')->nullable()->constrained('polling_units')->restrictOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->restrictOnDelete();
            $table->string('proposed_name', 255)->nullable();

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy_m', 8, 2);
            $table->decimal('distance_from_existing_m', 10, 2)->nullable();
            $table->dateTime('captured_at');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        DB::statement("
            ALTER TABLE polling_unit_submissions
            ADD CONSTRAINT chk_pu_sub_type_shape
            CHECK (
                (submission_type = 'coordinates'
                    AND polling_unit_id IS NOT NULL
                    AND ward_id IS NULL)
                OR
                (submission_type = 'new_polling_unit'
                    AND ward_id IS NOT NULL
                    AND proposed_name IS NOT NULL)
            )
        ");

        DB::statement("
            ALTER TABLE polling_unit_submissions
            ADD CONSTRAINT fk_pu_sub_submitter
            FOREIGN KEY (tenant_id, submitted_by) REFERENCES users(tenant_id, id)
        ");

        DB::statement("
            ALTER TABLE polling_unit_submissions
            ADD CONSTRAINT fk_pu_sub_reviewer
            FOREIGN KEY (tenant_id, reviewed_by) REFERENCES users(tenant_id, id)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_unit_submissions');
    }
};
