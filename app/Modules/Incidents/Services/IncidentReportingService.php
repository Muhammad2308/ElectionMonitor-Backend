<?php

namespace App\Modules\Incidents\Services;

use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IncidentReportingService
{
    public function report(array $data, $user): Incident
    {
        return DB::transaction(function () use ($data, $user) {
            return Incident::create([
                'id'              => $data['id'] ?? Str::uuid()->toString(),
                'reporter_id'     => $user->id,
                'polling_unit_id' => $data['polling_unit_id'],
                'category_id'     => $data['category_id'],
                'severity'        => $data['severity'] ?? 'medium',
                'status'          => 'open',
                'description'     => $data['description'] ?? null,
                'latitude'        => $data['latitude'] ?? null,
                'longitude'       => $data['longitude'] ?? null,
                'incident_time'   => $data['incident_time'] ?? now(),
                'sync_status'     => 'synced',
            ]);
        });
    }

    public function storeMedia(Incident $incident, $file, string $type = 'image'): IncidentMedia
    {
        $path = $file->store("incidents/{$incident->id}", 'public');

        return IncidentMedia::create([
            'id'           => Str::uuid()->toString(),
            'incident_id'  => $incident->id,
            'storage_path' => $path,
            'mime_type'    => $file->getMimeType(),
            'size_bytes'   => $file->getSize(),
            // sha256 (not md5) for evidence-integrity checking, computed
            // from the uploaded file itself.
            'sha256'       => hash_file('sha256', $file->getRealPath()),
        ]);
    }
}