<?php

namespace App\Modules\Observers\Models;

use App\Models\User;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Database\Eloquent\Model;

class ObserverCheckIn extends Model
{
    protected $fillable = [
        'user_id',
        'polling_unit_id',
        'check_in_time',
        'latitude',
        'longitude',
        'distance_from_pu',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'latitude'      => 'float',
        'longitude'     => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pollingUnit()
    {
        return $this->belongsTo(PollingUnit::class);
    }
}
