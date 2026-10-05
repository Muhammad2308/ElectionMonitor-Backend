<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Modules\ReferenceData\Models\State;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'state_id',
        'device_id',
        'phone',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function incidents()
    {
        return $this->hasMany(\App\Modules\Incidents\Models\Incident::class, 'reporter_id');
    }

    public function assignments()
    {
        return $this->hasMany(\App\Modules\Assignments\Models\ObserverAssignment::class, 'observer_id');
    }

    public function checkIns()
    {
        return $this->hasMany(\App\Modules\Observers\Models\ObserverCheckIn::class, 'observer_id');
    }

    public function gpsLocations()
    {
        return $this->hasMany(\App\Modules\Observers\Models\GpsLocation::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(\App\Modules\Audit\Models\ActivityLog::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}