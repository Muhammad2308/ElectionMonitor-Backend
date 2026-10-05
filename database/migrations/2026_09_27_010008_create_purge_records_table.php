<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M08 — purge_records
 *
 * Deletion certificates issued after a tenant's data is purged.
 * This table SURVIVES the purge — it contains no personal data.
 * It uses the tenant UUID (not FK) so it persists after the tenant row is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purge_records', function (Blueprint $table) {
            $table->id();

            $table->string('certificate_number', 50)->unique(); // "CS-DEL-2027-0001"
            $table->uuid('tenant_uuid');                        // snapshot; no FK (tenant is gone)
            $table->string('organisation_name');                // snapshot
            $table->string('tenant_name');                      // snapshot

            $table->dateTime('handed_over_at');
            $table->dateTime('purged_at');

            $table->foreignId('purged_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->json('row_counts');             // {"incidents":5120,"users":6700,...}
            $table->unsignedInteger('storage_objects');
            $table->text('backup_expiry_note');     // when the last backup containing the data expires

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purge_records');
    }
};
