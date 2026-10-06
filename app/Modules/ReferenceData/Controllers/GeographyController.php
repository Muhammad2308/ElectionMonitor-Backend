<?php

namespace App\Modules\ReferenceData\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ReferenceData\Models\State;
use App\Modules\ReferenceData\Models\Lga;
use App\Modules\ReferenceData\Models\Ward;
use App\Modules\ReferenceData\Models\PollingUnit;
use App\Modules\ReferenceData\Models\PollingUnitSubmission;
use App\Modules\ReferenceData\Resources\PollingUnitResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GeographyController extends Controller
{
    public function states()
    {
        return response()->json(State::all());
    }

    public function lgas(Request $request)
    {
        $user = $request->user();
        $query = Lga::query();

        $stateId = $request->input('state_id') ?: ($user && $user->state_id ? $user->state_id : null);
        if ($stateId) {
            $query->where('state_id', $stateId);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function wards(Request $request)
    {
        $user = $request->user();
        $query = Ward::query();

        if ($request->filled('lga_id')) {
            $query->where('lga_id', $request->integer('lga_id'));
        } elseif ($user && $user->state_id) {
            $query->whereHas('lga', function ($q) use ($user) {
                $q->where('state_id', $user->state_id);
            });
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function pollingUnits(Request $request)
    {
        $user = $request->user();
        $query = PollingUnit::with(['ward.lga', 'registeredBy']);

        // Scope to state
        $stateId = $request->input('state_id') ?: ($user && $user->state_id ? $user->state_id : null);
        if ($stateId) {
            $query->whereHas('ward.lga', function ($q) use ($stateId) {
                $q->where('state_id', $stateId);
            });
        }

        // Filter by LGA
        if ($request->filled('lga_id')) {
            $query->whereHas('ward', function ($q) use ($request) {
                $q->where('lga_id', $request->integer('lga_id'));
            });
        }

        // Filter by Ward
        if ($request->filled('ward_id')) {
            $query->where('ward_id', $request->integer('ward_id'));
        }

        // Filter by Registration status
        if ($request->has('is_registered') && $request->input('is_registered') !== '' && $request->input('is_registered') !== 'all') {
            $isReg = filter_var($request->input('is_registered'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_registered', $isReg);
        }

        // Search by PU code or name
        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('pu_code', 'like', $search);
            });
        }

        // If pagination requested (or by default when viewing large list)
        $perPage = $request->integer('per_page', 25);
        if ($request->boolean('all', false)) {
            return PollingUnitResource::collection($query->limit(500)->get());
        }

        $paginated = $query->orderBy('name')->paginate($perPage);
        return PollingUnitResource::collection($paginated);
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $stateId = $request->input('state_id') ?: ($user && $user->state_id ? $user->state_id : null);

        $puQuery = PollingUnit::query();
        if ($stateId) {
            $puQuery->whereHas('ward.lga', fn ($q) => $q->where('state_id', $stateId));
        }

        $total = (clone $puQuery)->count();
        $registered = (clone $puQuery)->where('is_registered', true)->count();
        $unregistered = $total - $registered;

        $pendingSubmissions = 0;
        if ($user && $user->tenant_id) {
            $pendingSubmissions = PollingUnitSubmission::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'pending')
                ->count();
        }

        return response()->json([
            'total'               => $total,
            'registered'          => $registered,
            'unregistered'        => $unregistered,
            'registered_pct'      => $total > 0 ? round(($registered / $total) * 100, 1) : 0,
            'pending_submissions' => $pendingSubmissions,
        ]);
    }

    public function register(Request $request, int $id)
    {
        $user = $request->user();
        abort_unless($user->can('polling-units.review'), 403, 'Permission denied.');

        $data = $request->validate([
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'name'        => ['nullable', 'string', 'max:255'],
            'image'       => ['nullable', 'file', 'mimes:jpeg,png,webp,jpg', 'max:10240'],
        ]);

        $pu = PollingUnit::with('ward.lga')->findOrFail($id);

        // Security check: state admin can only register PUs within their state
        if ($user->state_id && $pu->ward?->lga?->state_id !== $user->state_id) {
            abort(403, 'Cannot register a polling unit outside your state.');
        }

        $updates = [
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'is_registered' => true,
            'registered_at' => now(),
            'registered_by' => $user->id,
        ];

        if (!empty($data['name'])) {
            $updates['name'] = $data['name'];
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $tenantId = $user->tenant_id ?? 0;
            $path = $file->store("tenants/{$tenantId}/polling_units/{$pu->id}", 'private');
            $updates['image_path'] = $path;
        }

        $pu->update($updates);

        return response()->json([
            'message'      => 'Polling unit registered successfully.',
            'polling_unit' => new PollingUnitResource($pu->fresh(['ward.lga', 'registeredBy'])),
        ]);
    }

    public function image(Request $request, int $id)
    {
        $user = $request->user();
        $pu = PollingUnit::findOrFail($id);

        if (!$pu->image_path || !Storage::disk('private')->exists($pu->image_path)) {
            abort(404, 'Polling unit image not found.');
        }

        $mime = Storage::disk('private')->mimeType($pu->image_path) ?? 'image/jpeg';
        return Storage::disk('private')->response($pu->image_path, null, ['Content-Type' => $mime]);
    }
}
