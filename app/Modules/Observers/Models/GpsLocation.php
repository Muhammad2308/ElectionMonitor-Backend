<?php

namespace App\Modules\Observers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GpsLocation extends Model
{
    protected $fillable = ['user_id', 'latitude', 'longitude', 'battery_level', 'captured_at'];

    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'captured_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
