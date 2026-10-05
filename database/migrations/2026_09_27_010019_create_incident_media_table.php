<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M19 — create_incident_media_table
 *
 * Rebuilds incident_media as tenant-scoped. If a pre-v3 incident_media table
 * exists, its rows are migrated into the new shape (not dropped outright):
 * old columns were media_type/file_path/file_hash/metadata; new columns are
 * storage_path/mime_type/size_bytes/sha256. incidents.id is a UUID, so
 * incident_media.id and .incident_id are UUIDs too, matching the original
 * 2026_06_14_164254 schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasLegacyTable = Schema::hasTable('incident_media');

        if ($hasLegacyTable) {
            Schema::rename('incident_media', 'incident_media_legacy');
        }

        Schema::create('incident_media', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->restrictOnDelete();

            $table->uuid('incident_id'); // matches incidents.id (UUID primary key)

            $table->string('storage_path', 500); // tenants/{tenant_uuid}/incidents/...
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->char('sha256', 64);

            $table->timestamps();
        });

        if ($hasLegacyTable) {
            $legacyRows = DB::table('incident_media_legacy')
                ->join('incidents', 'incidents.id', '=', 'incident_media_legacy.incident_id')
                ->select('incident_media_legacy.*', 'incidents.tenant_id as incident_tenant_id')
                ->get();

            $mimeByType = [
                'image' => 'image/jpeg',
                'audio' => 'audio/mpeg',
                'video' => 'video/mp4',
            ];

            foreach ($legacyRows as $row) {
                // Legacy rows predate tenant scoping; skip any whose incident
                // never received a tenant_id from M18 rather than guess one.
                if ($row->incident_tenant_id === null) {
                    continue;
                }

                $hash = $row->file_hash;
                if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
                    // Legacy hash isn't a sha256 digest — derive one so the
                    // column constraint holds. This is a one-time backfill of
                    // pre-tenancy data, not a claim about file integrity.
                    $hash = hash('sha256', (string) ($hash ?? $row->id));
                }

                DB::table('incident_media')->insert([
                    'id' => $row->id,
                    'tenant_id' => $row->incident_tenant_id,
                    'incident_id' => $row->incident_id,
                    'storage_path' => $row->file_path,
                    'mime_type' => $mimeByType[$row->media_type] ?? 'application/octet-stream',
                    'size_bytes' => 0, // not tracked by the legacy schema
                    'sha256' => $hash,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::dropIfExists('incident_media_legacy');
        }

        DB::statement("
            ALTER TABLE incident_media
            ADD CONSTRAINT fk_inc_media_incident
            FOREIGN KEY (tenant_id, incident_id) REFERENCES incidents(tenant_id, id)
            ON DELETE CASCADE
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_media');
    }
};
