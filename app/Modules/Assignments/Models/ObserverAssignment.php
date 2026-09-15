<?php

namespace App\Modules\Assignments\Models;

use App\Models\User;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Database\Eloquent\Model;

class ObserverAssignment extends Model
{
    protected $fillable = ['user_id', 'polling_unit_id', 'election_date'];

    protected $casts = [
        'election_date' => 'date',
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
