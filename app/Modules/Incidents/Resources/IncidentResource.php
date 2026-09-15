<?php

namespace App\Modules\Incidents\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'category_id'     => $this->category_id,
            'category_name'   => $this->category?->name,
            'severity'        => $this->severity,
            'status'          => $this->status,
            'polling_unit_id' => $this->polling_unit_id,
            'polling_unit'    => $this->whenLoaded('pollingUnit', fn () => [
                'id'      => $this->pollingUnit->id,
                'pu_code' => $this->pollingUnit->pu_code,
                'name'    => $this->pollingUnit->name,
                'ward'    => $this->pollingUnit->ward?->name,
                'lga'     => $this->pollingUnit->ward?->lga?->name,
                'state'   => $this->pollingUnit->ward?->lga?->state?->name,
            ]),
            'reporter'        => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'description'     => $this->description,
            'incident_time'   => $this->incident_time?->toIso8601String(),
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'sync_status'     => $this->sync_status,
            'media_count'     => $this->whenLoaded('media', fn () => $this->media->count()),
            'media'           => IncidentMediaResource::collection($this->whenLoaded('media')),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
