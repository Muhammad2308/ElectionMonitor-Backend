<?php

namespace App\Modules\Incidents\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class IncidentMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            // media_type no longer exists as its own column (rebuilt by
            // 2026_09_27_010019_create_incident_media_table) — derive it
            // from mime_type (e.g. "image/jpeg" -> "image").
            'media_type' => $this->mime_type ? strtok($this->mime_type, '/') : null,
            'mime_type'  => $this->mime_type,
            'url'        => Storage::url($this->storage_path),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
