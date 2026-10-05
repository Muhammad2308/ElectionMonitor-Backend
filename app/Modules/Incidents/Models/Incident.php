<?php

namespace App\Modules\Incidents\Models;

use App\Models\User;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Incident extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'reporter_id',
        'polling_unit_id',
        'category_id',
        'severity',
        'status',
        'description',
        'involving_party',
        'incident_time',
        'latitude',
        'longitude',
        'sync_status',
    ];

    protected $casts = [
        'latitude'      => 'float',
        'longitude'     => 'float',
        'incident_time' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'investigating']);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('state_tenant', function (Builder $builder) {
            // API requests authenticate via the 'sanctum' guard, not the
            // app's default 'web' guard — auth()->user() would silently
            // resolve to null here and disable this scope on every request.
            $user = auth('sanctum')->user();
            if ($user && $user->state_id) {
                $builder->whereHas('pollingUnit.ward.lga', function ($q) use ($user) {
                    $q->where('state_id', $user->state_id);
                });
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function pollingUnit()
    {
        return $this->belongsTo(PollingUnit::class);
    }

    public function category()
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    public function media()
    {
        return $this->hasMany(IncidentMedia::class);
    }
}
