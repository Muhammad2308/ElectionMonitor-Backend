<?php

namespace App\Modules\Inbox\Services;

use App\Models\User;
use App\Modules\Inbox\Models\InboxNotification;
use Illuminate\Support\Str;

class InboxService
{
    /**
     * Platform users (no tenant) have no inbox, so notifications for them are skipped.
     */
    public function notify(User $recipient, string $title, string $body, array $data = [], string $priority = 'normal'): void
    {
        if ($recipient->tenant_id === null) {
            return;
        }

        InboxNotification::create([
            'id'        => (string) Str::uuid(),
            'tenant_id' => $recipient->tenant_id,
            'user_id'   => $recipient->id,
            'title'     => $title,
            'body'      => $body,
            'data'      => $data,
            'priority'  => $priority,
        ]);
    }
}
