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
                'user_id'         => $user->id,
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
            'id'          => Str::uuid()->toString(),
            'incident_id' => $incident->id,
            'media_type'  => $type,
            'file_path'   => $path,
            'file_hash'   => md5_file($file->path()),
        ]);
    }
}