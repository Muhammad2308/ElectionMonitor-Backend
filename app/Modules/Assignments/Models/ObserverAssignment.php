<?php

namespace App\Modules\Assignments\Models;

use App\Models\User;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Database\Eloquent\Model;

class ObserverAssignment extends Model
{
    protected $fillable = ['tenant_id', 'observer_id', 'polling_unit_id', 'election_date', 'assigned_by'];

    protected $casts = [
        'election_date' => 'date',
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
