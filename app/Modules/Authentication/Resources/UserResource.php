<?php

namespace App\Modules\Authentication\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roleName = $this->role_type 
            ?? $this->getRoleNames()->first() 
            ?? $this->roles()->pluck('name')->first();

        $roleNames = $this->getRoleNames();
        if ($roleNames->isEmpty()) {
            $direct = $this->roles()->pluck('name');
            $roleNames = $direct->isNotEmpty() ? $direct : ($this->role_type ? collect([$this->role_type]) : collect());
        }

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'state_id'          => $this->state_id,
            'state_name'        => $this->state?->name,
            'status'            => $this->status,
            'role'              => $roleName,
            'role_type'         => $this->role_type ?? $roleName,
            'roles'             => $roleNames,
            'incidents_count'   => $this->whenCounted('incidents'),
            'assignments_count' => $this->whenCounted('assignments'),
            'check_ins_count'   => $this->whenCounted('checkIns'),
        ];
    }
}
