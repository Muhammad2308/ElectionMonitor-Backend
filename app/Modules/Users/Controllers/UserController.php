<?php

namespace App\Modules\Users\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authentication\Resources\UserResource;
use App\Modules\Audit\Models\ActivityLog;
use App\Modules\Inbox\Services\InboxService;
use App\Services\UserCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const TENANT_ROLES = ['observer', 'state_admin', 'state_master_admin'];

    public function index(Request $request)
    {
        abort_unless($request->user()->can('users.view'), 403);

        $query = User::with('state')->withCount(['incidents', 'assignments', 'checkIns']);

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('state_id')) {
            $query->where('state_id', $request->integer('state_id'));
        }

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
            });
        }

        $users = $query->orderByDesc('created_at')->paginate($request->integer('limit', 20));

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        abort_unless($admin->can('users.create'), 403);
        abort_if($admin->tenant_id === null, 403, 'Platform accounts cannot register tenant users.');

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'role'     => ['required', 'string', Rule::in(self::TENANT_ROLES)],
        ]);

        $allowedRoles = $admin->hasRole('state_admin') ? ['observer'] : self::TENANT_ROLES;
        abort_unless(in_array($data['role'], $allowedRoles, true), 403, 'Your role cannot create this account type.');

        $tenant = DB::table('tenants as t')
            ->join('organisations as o', 'o.id', '=', 't.organisation_id')
            ->where('t.id', $admin->tenant_id)
            ->first(['t.id', 't.state_id', 't.organisation_id', 'o.short_code']);

        $stateId = $tenant->state_id ?? $data['state_id'] ?? null;
        if ($stateId === null) {
            throw ValidationException::withMessages(['state_id' => ['A state is required for this account.']]);
        }
        $stateCode = DB::table('states')->where('id', $stateId)->value('iso_code') ?? 'NG';

        $user = DB::transaction(function () use ($data, $tenant, $stateId, $stateCode, $admin) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'state_id' => $stateId,
                'status'   => 'active',
            ]);

            $user->forceFill([
                'tenant_id'       => $tenant->id,
                'organisation_id' => $tenant->organisation_id,
                'role_type'       => $data['role'],
                'user_code'       => UserCodeGenerator::generate($tenant->id, $tenant->short_code, $stateCode, $data['role']),
                'supervisor_id'   => $admin->id,
                'created_by'      => $admin->id,
            ])->save();

            setPermissionsTeamId($tenant->id);
            $user->assignRole($data['role']);
            setPermissionsTeamId($admin->tenant_id);

            return $user;
        });

        ActivityLog::record('user.created', 'User', (string) $user->id, [], array_diff_key($data, ['password' => true]));

        app(InboxService::class)->notify(
            $user,
            'Welcome to ElectionWatch',
            "{$admin->name} registered you as {$data['role']}. Your user code is {$user->user_code}.",
            ['type' => 'account_created', 'user_code' => $user->user_code],
            'high'
        );

        return response()->json(['message' => 'User created.', 'user' => new UserResource($user->load('state'))], 201);
    }

    public function show(string $id)
    {
        abort_unless(request()->user()->can('users.view'), 403);

        $user = User::with('state')->withCount(['incidents', 'assignments', 'checkIns'])->findOrFail($id);

        return new UserResource($user);
    }

    public function update(Request $request, string $id)
    {
        abort_unless($request->user()->can('users.update'), 403);

        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'phone'    => ['nullable', 'string', 'max:30'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
        ]);

        $old = $user->only(array_keys($data));
        $user->update($data);

        ActivityLog::record('user.updated', 'User', (string) $user->id, $old, $data);

        return response()->json(['message' => 'User updated.', 'user' => new UserResource($user->load('state'))]);
    }

    public function destroy(string $id)
    {
        abort_unless(request()->user()->can('users.delete'), 403);

        $user = User::findOrFail($id);
        $user->delete();

        ActivityLog::record('user.deleted', 'User', (string) $id);

        return response()->json(['message' => 'User deleted.']);
    }

    public function suspend(string $id)
    {
        abort_unless(request()->user()->can('users.suspend'), 403);

        $user = User::findOrFail($id);
        $newStatus = $user->status === 'suspended' ? 'active' : 'suspended';
        $user->update(['status' => $newStatus]);

        ActivityLog::record('user.' . $newStatus, 'User', (string) $user->id);

        return response()->json(['message' => "User {$newStatus}.", 'user' => new UserResource($user)]);
    }

    public function assignRole(Request $request, string $id)
    {
        abort_unless($request->user()->can('users.assign-role'), 403);

        $data = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($id);
        $user->syncRoles([$data['role']]);

        ActivityLog::record('user.role-assigned', 'User', (string) $user->id, [], $data);

        return response()->json(['message' => 'Role assigned.', 'user' => new UserResource($user)]);
    }
}
