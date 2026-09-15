<?php

namespace App\Modules\Incidents\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Requests\ReportIncidentRequest;
use App\Modules\Incidents\Resources\IncidentResource;
use App\Modules\Incidents\Services\IncidentReportingService;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    protected $reportingService;

    public function __construct(IncidentReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * List incidents with optional filters (paginated).
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->can('incidents.view'), 403, 'Missing incidents.view permission.');

        $query = Incident::query()->with(['category', 'pollingUnit.ward.lga.state', 'user']);

        if (! $request->user()->can('incidents.view-all')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('state_id')) {
            $query->whereHas('pollingUnit.ward.lga', function ($q) use ($request) {
                $q->where('state_id', $request->integer('state_id'));
            });
        }

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where('description', 'like', "%{$term}%");
        }

        $incidents = $query->orderByDesc('incident_time')
            ->paginate($request->integer('limit', 20));

        return IncidentResource::collection($incidents);
    }

    /**
     * Show a single incident.
     */
    public function show(string $id)
    {
        $incident = Incident::with(['category', 'pollingUnit.ward.lga.state', 'user', 'media'])
            ->findOrFail($id);

        return new IncidentResource($incident);
    }

    /**
     * Store a new incident report.
     */
    public function store(ReportIncidentRequest $request)
    {
        $incident = $this->reportingService->report($request->validated(), $request->user());

        return response()->json([
            'message' => 'Incident reported successfully.',
            'incident' => new IncidentResource($incident),
        ], 201);
    }

    /**
     * Upload media for an existing incident.
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'incident_id' => 'required|exists:incidents,id',
            'file' => 'required|image|max:10240', // 10MB limit
            'type' => 'nullable|string'
        ]);

        $incident = Incident::findOrFail($request->incident_id);

        $media = $this->reportingService->storeMedia(
            $incident,
            $request->file('file'),
            $request->type ?? 'image'
        );

        return response()->json([
            'message' => 'Media uploaded successfully.',
            'media' => $media
        ], 201);
    }
}
