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
            'media_type' => $this->media_type,
            'url'        => Storage::url($this->file_path),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
