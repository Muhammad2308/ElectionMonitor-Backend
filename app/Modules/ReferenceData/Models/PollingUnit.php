<?php

namespace App\Modules\ReferenceData\Models;

use Illuminate\Database\Eloquent\Model;

class PollingUnit extends Model
{
    protected $fillable = ['ward_id', 'pu_code', 'name', 'latitude', 'longitude'];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

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
