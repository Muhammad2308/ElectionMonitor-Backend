<?php

namespace App\Modules\Roles\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Users-per-role counts, computed directly off the pivot table.
     *
     * Note: deliberately not using Role::users()/withCount('users') here —
     * spatie's morphedByMany relation resolves the guard's user model via
     * config('auth.guards.*.provider') at call time, which throws
     * ("Class name must be a valid object or a string") when invoked from
     * inside the HTTP kernel in this environment. A direct pivot-table
     * count sidesteps that relation entirely.
     */
    private function userCountsByRole(): array
    {
        return DB::table('model_has_roles')
            ->select('role_id', DB::raw('count(*) as total'))
            ->where('model_type', User::class)
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->toArray();
    }

    public function index(Request $request)
    {
        $counts = $this->userCountsByRole();

        $roles = Role::with('permissions')->get()->map(fn ($role) => [
            'id'          => $role->id,
            'name'        => $role->name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $counts[$role->id] ?? 0,
            'created_at'  => $role->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $roles]);
    }

    public function show(string $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $counts = $this->userCountsByRole();

        return response()->json([
            'id'          => $role->id,
            'name'        => $role->name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $counts[$role->id] ?? 0,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('users.assign-role'), 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'unique:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json(['message' => 'Role created.', 'role' => $role->load('permissions')], 201);
    }

    public function update(Request $request, string $id)
    {
        abort_unless($request->user()->can('users.assign-role'), 403);

        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'unique:roles,name,' . $role->id],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json(['message' => 'Role updated.', 'role' => $role->load('permissions')]);
    }

    public function destroy(string $id)
    {
        abort_unless(request()->user()->can('users.assign-role'), 403);

        Role::findOrFail($id)->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
