<?php

namespace App\Modules\Incidents\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentMedia extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'incident_id',
        'media_type',
        'file_path',
        'file_hash',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }
}
