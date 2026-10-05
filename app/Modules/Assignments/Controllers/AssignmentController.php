<?php

namespace App\Modules\Assignments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assignments\Models\ObserverAssignment;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    /**
     * Observers in the admin's own tenant. Other tenants' users are never returned.
     *
     * @param  array<int>  $ids
     * @return Collection<int, User>
     */
    private function tenantObservers(User $admin, array $ids): Collection
    {
        abort_if($admin->tenant_id === null, 403, 'No tenant context.');

        return User::query()
            ->whereIn('id', $ids)
            ->where('tenant_id', $admin->tenant_id)
            ->where('role_type', 'observer')
            ->get();
    }

    private function notifyAssigned(User $observer, PollingUnit $pollingUnit, string $electionDate): void
    {
        app(InboxService::class)->notify(
            $observer,
            'New polling unit assignment',
            "You are assigned to {$pollingUnit->name} ({$pollingUnit->pu_code}) for the election on {$electionDate}.",
            ['type' => 'assignment', 'polling_unit_id' => $pollingUnit->id, 'election_date' => $electionDate],
            'high'
        );
    }

    public function mine(Request $request)
    {
        $assignments = ObserverAssignment::with('pollingUnit.ward.lga.state')
            ->where('observer_id', $request->user()->id)
            ->orderByDesc('election_date')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('assignments.view'), 403);

        $query = ObserverAssignment::with(['user', 'pollingUnit.ward.lga.state']);

        if ($request->filled('user_id')) {
            $query->where('observer_id', $request->integer('user_id'));
        }

        if ($request->filled('polling_unit_id')) {
            $query->where('polling_unit_id', $request->integer('polling_unit_id'));
        }

        if ($request->filled('election_date')) {
            $query->whereDate('election_date', $request->date('election_date'));
        }

        $assignments = $query->orderByDesc('election_date')->paginate($request->integer('limit', 20));

        return response()->json($assignments);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('assignments.manage'), 403);

        $data = $request->validate([
            'user_id'         => ['required', 'integer', 'exists:users,id'],
            'polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
            'election_date'   => ['required', 'date'],
        ]);

        $admin = $request->user();
        $observer = $this->tenantObservers($admin, [$data['user_id']])->first()
            ?? abort(422, 'That user is not an observer in your tenant.');
        $pollingUnit = PollingUnit::findOrFail($data['polling_unit_id']);

        $assignment = ObserverAssignment::create([
            'tenant_id'       => $observer->tenant_id,
            'observer_id'     => $observer->id,
            'polling_unit_id' => $pollingUnit->id,
            'election_date'   => $data['election_date'],
            'assigned_by'     => $admin->id,
        ]);

        $this->notifyAssigned($observer, $pollingUnit, $data['election_date']);

        return response()->json(['message' => 'Assignment created.', 'assignment' => $assignment], 201);
    }

    public function bulk(Request $request)
    {
        abort_unless($request->user()->can('assignments.manage'), 403);

        $data = $request->validate([
            'election_date'      => ['required', 'date'],
            'assignments'        => ['required', 'array', 'min:1'],
            'assignments.*.user_id'         => ['required', 'integer', 'exists:users,id'],
            'assignments.*.polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
        ]);

        $admin = $request->user();
        $observers = $this->tenantObservers($admin, collect($data['assignments'])->pluck('user_id')->unique()->all())->keyBy('id');

        $missing = collect($data['assignments'])->pluck('user_id')->unique()->diff($observers->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => ['Users not in your tenant as observers: ' . $missing->implode(', ')],
            ]);
        }

        $pollingUnits = PollingUnit::whereIn('id', collect($data['assignments'])->pluck('polling_unit_id')->unique())->get()->keyBy('id');

        $rows = collect($data['assignments'])->map(fn ($row) => [
            'tenant_id'       => $observers[$row['user_id']]->tenant_id,
            'observer_id'     => $row['user_id'],
            'polling_unit_id' => $row['polling_unit_id'],
            'election_date'   => $data['election_date'],
            'assigned_by'     => $admin->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->all();

        DB::transaction(fn () => ObserverAssignment::insert($rows));

        foreach ($data['assignments'] as $row) {
            $this->notifyAssigned($observers[$row['user_id']], $pollingUnits[$row['polling_unit_id']], $data['election_date']);
        }

        return response()->json(['message' => count($rows) . ' assignments created.'], 201);
    }

    public function destroy(string $id)
    {
        abort_unless(request()->user()->can('assignments.manage'), 403);

        $assignment = ObserverAssignment::findOrFail($id);
        $assignment->delete();

        return response()->json(['message' => 'Assignment removed.']);
    }
}
