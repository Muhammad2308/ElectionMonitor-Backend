<?php

namespace App\Modules\Incidents\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentMedia extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    // Rebuilt by 2026_09_27_010019_create_incident_media_table: media_type/
    // file_path/file_hash/metadata are gone in favour of storage_path/
    // mime_type/size_bytes/sha256.
    protected $fillable = [
        'id',
        'incident_id',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sha256',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }
}
