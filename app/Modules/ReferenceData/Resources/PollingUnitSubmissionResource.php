<?php

namespace App\Modules\ReferenceData\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollingUnitSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'uuid'                     => $this->uuid,
            'submission_type'          => $this->submission_type,
            'status'                   => $this->status,
            'latitude'                 => $this->latitude,
            'longitude'                => $this->longitude,
            'accuracy_m'               => $this->accuracy_m,
            'distance_from_existing_m' => $this->distance_from_existing_m,
            'captured_at'              => $this->captured_at?->toIso8601String(),
            'proposed_name'            => $this->proposed_name,
            'polling_unit_id'          => $this->polling_unit_id,
            'polling_unit'             => $this->whenLoaded('pollingUnit', fn () => [
                'id'        => $this->pollingUnit->id,
                'pu_code'   => $this->pollingUnit->pu_code,
                'name'      => $this->pollingUnit->name,
                'latitude'  => $this->pollingUnit->latitude !== null ? (float) $this->pollingUnit->latitude : null,
                'longitude' => $this->pollingUnit->longitude !== null ? (float) $this->pollingUnit->longitude : null,
            ]),
            'ward'                     => $this->whenLoaded('ward', fn () => [
                'id'       => $this->ward->id,
                'name'     => $this->ward->name,
                'lga_name' => $this->ward->lga?->name,
            ]),
            'submitter'                => $this->whenLoaded('submitter', fn () => [
                'id'        => $this->submitter->id,
                'name'      => $this->submitter->name,
                'email'     => $this->submitter->email,
                'user_code' => $this->submitter->user_code,
                'role_type' => $this->submitter->role_type,
            ]),
            'photos'                   => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id'         => $photo->id,
                'mime_type'  => $photo->mime_type,
                'size_bytes' => $photo->size_bytes,
                'url'        => "/polling-units/submissions/{$this->id}/photos/{$photo->id}",
            ])->values()),
            'reviewed_at'              => $this->reviewed_at?->toIso8601String(),
            'review_note'              => $this->review_note,
            'created_at'               => $this->created_at?->toIso8601String(),
        ];
    }
}
