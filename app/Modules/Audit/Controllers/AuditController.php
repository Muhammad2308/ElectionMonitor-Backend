<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\ActivityLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $query = ActivityLog::with('user:id,name,email');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        $logs = $query->orderByDesc('created_at')->paginate($request->integer('limit', 25));

        return response()->json($logs);
    }
}
