<?php

namespace App\Modules\Inbox\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Models\InboxNotification;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $base = InboxNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $request->user()->tenant_id);

        $items = (clone $base)->latest('created_at')->limit(50)->get();

        return response()->json([
            'data'         => $items,
            'unread_count' => (clone $base)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $item = InboxNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $item->forceFill(['read_at' => now()])->save();

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        InboxNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked as read.']);
    }
}
