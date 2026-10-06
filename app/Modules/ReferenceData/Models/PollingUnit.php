<?php

namespace App\Modules\ReferenceData\Models;

use Illuminate\Database\Eloquent\Model;

class PollingUnit extends Model
{
    protected $fillable = [
        'ward_id',
        'pu_code',
        'name',
        'latitude',
        'longitude',
        'image_path',
        'is_registered',
        'registered_at',
        'registered_by',
    ];

    protected $casts = [
        'latitude'      => 'float',
        'longitude'     => 'float',
        'is_registered' => 'boolean',
        'registered_at' => 'datetime',
    ];

    public function registeredBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'registered_by');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function incidents()
    {
        return $this->hasMany(\App\Modules\Incidents\Models\Incident::class);
    }

    public function assignments()
    {
        return $this->hasMany(\App\Modules\Assignments\Models\ObserverAssignment::class);
    }

    public function checkIns()
    {
        return $this->hasMany(\App\Modules\Observers\Models\ObserverCheckIn::class);
    }
}
