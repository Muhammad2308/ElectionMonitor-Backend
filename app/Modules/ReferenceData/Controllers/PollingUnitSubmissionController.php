<?php

namespace App\Modules\ReferenceData\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ReferenceData\Models\PollingUnitSubmission;
use App\Modules\ReferenceData\Requests\StorePollingUnitSubmissionRequest;
use App\Modules\ReferenceData\Resources\PollingUnitSubmissionResource;
use App\Modules\ReferenceData\Services\PollingUnitSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PollingUnitSubmissionController extends Controller
{
    public function __construct(private PollingUnitSubmissionService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('polling-units.review'), 403, 'Missing polling-units.review permission.');
        abort_if($request->user()->tenant_id === null, 403, 'No tenant context.');

        $status = $request->query('status', PollingUnitSubmission::STATUS_PENDING);
        $request->validate(['status' => ['nullable', 'in:pending,approved,rejected']]);

        $submissions = PollingUnitSubmission::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('status', $status)
            ->with(['submitter', 'pollingUnit', 'ward.lga', 'photos'])
            ->latest('captured_at')
            ->limit(200)
            ->get();

        return PollingUnitSubmissionResource::collection($submissions);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->can('polling-units.submit'), 403, 'Missing polling-units.submit permission.');

        $submissions = PollingUnitSubmission::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('submitted_by', $request->user()->id)
            ->with(['pollingUnit', 'ward.lga', 'photos'])
            ->latest('captured_at')
            ->limit(100)
            ->get();

        return PollingUnitSubmissionResource::collection($submissions);
    }

    public function store(StorePollingUnitSubmissionRequest $request)
    {
        $submission = $this->service->submit(
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', [])
        );

        return (new PollingUnitSubmissionResource($submission->load(['pollingUnit', 'ward.lga', 'photos'])))
            ->additional(['message' => 'Location submitted for review.'])
            ->response()
            ->setStatusCode(201);
    }

    public function approve(Request $request, int $id)
    {
        $reviewer = $request->user();
        abort_unless($reviewer->can('polling-units.review'), 403, 'Missing polling-units.review permission.');

        $data = $request->validate([
            'pu_code' => ['nullable', 'string', 'max:50'],
            'name'    => ['nullable', 'string', 'max:255'],
        ]);

        $submission = $this->findForTenant($reviewer->tenant_id, $id);
        $approved = $this->service->approve($submission, $reviewer, $data['pu_code'] ?? null, $data['name'] ?? null);

        return new PollingUnitSubmissionResource($approved->load(['submitter', 'pollingUnit', 'ward.lga', 'photos']));
    }

    public function reject(Request $request, int $id)
    {
        $reviewer = $request->user();
        abort_unless($reviewer->can('polling-units.review'), 403, 'Missing polling-units.review permission.');

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:500'],
        ]);

        $submission = $this->findForTenant($reviewer->tenant_id, $id);
        $rejected = $this->service->reject($submission, $reviewer, $data['review_note']);

        return new PollingUnitSubmissionResource($rejected->load(['submitter', 'pollingUnit', 'ward.lga', 'photos']));
    }

    public function photo(Request $request, int $id, int $photoId)
    {
        $user = $request->user();
        abort_if($user->tenant_id === null, 403, 'No tenant context.');

        $submission = PollingUnitSubmission::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($id);

        $canReview = $user->can('polling-units.review');
        abort_unless($canReview || $submission->submitted_by === $user->id, 403, 'Not allowed to view this evidence.');

        $photo = $submission->photos()->findOrFail($photoId);

        return Storage::disk(PollingUnitSubmissionService::PHOTO_DISK)->response(
            $photo->storage_path,
            null,
            [
                'Content-Type'  => $photo->mime_type,
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    private function findForTenant(?int $tenantId, int $id): PollingUnitSubmission
    {
        abort_if($tenantId === null, 403, 'No tenant context.');

        return PollingUnitSubmission::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }
}
