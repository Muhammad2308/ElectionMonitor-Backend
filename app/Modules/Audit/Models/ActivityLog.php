<?php

namespace App\Modules\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'entity_name',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?string $entityName = null, ?string $entityId = null, array $old = [], array $new = []): self
    {
        return static::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'entity_name' => $entityName,
            'entity_id'   => $entityId,
            'old_values'  => $old ?: null,
            'new_values'  => $new ?: null,
            'ip_address'  => request()?->ip(),
        ]);
    }
}
