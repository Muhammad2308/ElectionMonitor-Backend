<?php

namespace App\Modules\Authentication\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'state_id'   => $this->state_id,
            'state_name' => $this->state?->name,
            'status'     => $this->status,
            'role'       => $this->getRoleNames()->first(),
            'roles'      => $this->getRoleNames(),
            'incidents_count'   => $this->whenCounted('incidents'),
            'assignments_count' => $this->whenCounted('assignments'),
            'check_ins_count'   => $this->whenCounted('checkIns'),
        ];
    }
}
