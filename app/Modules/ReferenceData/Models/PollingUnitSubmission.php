<?php

namespace App\Modules\ReferenceData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PollingUnitSubmission extends Model
{
    public const TYPE_COORDINATES = 'coordinates';
    public const TYPE_NEW = 'new_polling_unit';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'submitted_by',
        'submission_type',
        'polling_unit_id',
        'ward_id',
        'proposed_name',
        'latitude',
        'longitude',
        'accuracy_m',
        'distance_from_existing_m',
        'captured_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'latitude'                 => 'float',
        'longitude'                => 'float',
        'accuracy_m'               => 'float',
        'distance_from_existing_m' => 'float',
        'captured_at'              => 'datetime',
        'reviewed_at'              => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pollingUnit()
    {
        return $this->belongsTo(PollingUnit::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function photos()
    {
        return $this->hasMany(PollingUnitSubmissionPhoto::class, 'submission_id')->orderBy('id');
    }
}
