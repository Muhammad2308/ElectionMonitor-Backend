<?php

namespace App\Modules\Roles\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Permission::query();

        if ($request->filled('resource')) {
            $query->where('name', 'like', $request->string('resource') . '.%');
        }

        $permissions = $query->orderBy('name')->get()->map(fn ($p) => [
            'id'       => $p->id,
            'name'     => $p->name,
            'resource' => explode('.', $p->name)[0] ?? $p->name,
            'action'   => explode('.', $p->name)[1] ?? '',
        ]);

        return response()->json(['data' => $permissions]);
    }
}
