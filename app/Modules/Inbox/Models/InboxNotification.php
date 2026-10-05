<?php

namespace App\Modules\Inbox\Models;

use Illuminate\Database\Eloquent\Model;

class InboxNotification extends Model
{
    protected $table = 'user_notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'tenant_id', 'user_id', 'title', 'body', 'data', 'priority', 'read_at'];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];
}
