<?php

namespace App\Modules\Assignments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assignments\Models\ObserverAssignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function mine(Request $request)
    {
        $assignments = ObserverAssignment::with('pollingUnit.ward.lga.state')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('election_date')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('assignments.view'), 403);

        $query = ObserverAssignment::with(['user', 'pollingUnit.ward.lga.state']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
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
        abort_unless($request->user()->can('assignments.create'), 403);

        $data = $request->validate([
            'user_id'         => ['required', 'integer', 'exists:users,id'],
            'polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
            'election_date'   => ['required', 'date'],
        ]);

        $assignment = ObserverAssignment::create($data);

        return response()->json(['message' => 'Assignment created.', 'assignment' => $assignment], 201);
    }

    public function bulk(Request $request)
    {
        abort_unless($request->user()->can('assignments.bulk-create'), 403);

        $data = $request->validate([
            'election_date'      => ['required', 'date'],
            'assignments'        => ['required', 'array', 'min:1'],
            'assignments.*.user_id'         => ['required', 'integer', 'exists:users,id'],
            'assignments.*.polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
        ]);

        $rows = collect($data['assignments'])->map(fn ($row) => [
            'user_id'         => $row['user_id'],
            'polling_unit_id' => $row['polling_unit_id'],
            'election_date'   => $data['election_date'],
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->all();

        ObserverAssignment::insert($rows);

        return response()->json(['message' => count($rows) . ' assignments created.'], 201);
    }

    public function destroy(string $id)
    {
        abort_unless(request()->user()->can('assignments.delete'), 403);

        $assignment = ObserverAssignment::findOrFail($id);
        $assignment->delete();

        return response()->json(['message' => 'Assignment removed.']);
    }
}
