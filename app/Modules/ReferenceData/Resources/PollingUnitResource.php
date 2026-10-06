<?php

namespace App\Modules\ReferenceData\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollingUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'ward_id'   => $this->ward_id,
            'pu_code'   => $this->pu_code,
            'name'      => $this->name,
            'latitude'      => $this->latitude ? (float) $this->latitude : null,
            'longitude'     => $this->longitude ? (float) $this->longitude : null,
            'image_url'     => $this->image_path ? url('/api/v1/polling-units/' . $this->id . '/image') : null,
            'is_registered' => (bool) $this->is_registered,
            'registered_at' => $this->registered_at?->toISOString(),
            'registered_by' => $this->whenLoaded('registeredBy', fn () => $this->registeredBy ? [
                'id'   => $this->registeredBy->id,
                'name' => $this->registeredBy->name,
            ] : null),
            'ward_name'     => $this->whenLoaded('ward', fn () => $this->ward->name),
            'lga_name'      => $this->whenLoaded('ward', fn () => $this->ward->lga?->name),
            'lga_id'        => $this->whenLoaded('ward', fn () => $this->ward->lga_id),
        ];
    }
}
