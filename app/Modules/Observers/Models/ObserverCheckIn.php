<?php

namespace App\Modules\Observers\Models;

use App\Models\User;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Database\Eloquent\Model;

class ObserverCheckIn extends Model
{
    // The underlying table was renamed observer_check_ins -> check_ins
    // (2026_09_27_010020_add_tenant_to_check_ins_table).
    protected $table = 'check_ins';

    protected $fillable = [
        'uuid',
        'observer_id',
        'polling_unit_id',
        'captured_at',
        'synced_at',
        'latitude',
        'longitude',
        'accuracy_m',
        'distance_from_pu',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'synced_at'   => 'datetime',
        'latitude'    => 'float',
        'longitude'   => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'observer_id');
    }

    public function pollingUnit()
    {
        return $this->belongsTo(PollingUnit::class);
    }
}
