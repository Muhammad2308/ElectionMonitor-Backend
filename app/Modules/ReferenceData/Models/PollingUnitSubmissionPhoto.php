<?php

namespace App\Modules\ReferenceData\Models;

use Illuminate\Database\Eloquent\Model;

class PollingUnitSubmissionPhoto extends Model
{
    protected $fillable = [
        'tenant_id',
        'submission_id',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sha256',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function submission()
    {
        return $this->belongsTo(PollingUnitSubmission::class, 'submission_id');
    }
}
