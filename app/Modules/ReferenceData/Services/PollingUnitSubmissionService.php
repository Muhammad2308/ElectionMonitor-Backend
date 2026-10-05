<?php

namespace App\Modules\ReferenceData\Services;

use App\Models\User;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\ReferenceData\Models\PollingUnit;
use App\Modules\ReferenceData\Models\PollingUnitSubmission;
use App\Modules\ReferenceData\Models\PollingUnitSubmissionPhoto;
use App\Modules\ReferenceData\Models\Ward;
use App\Support\Geo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PollingUnitSubmissionService
{
    // Private disk: evidence is only served through the authorised photo endpoint.
    public const PHOTO_DISK = 'local';

    private const PHOTO_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function submit(User $observer, array $data, array $photos): PollingUnitSubmission
    {
        $distance = null;

        if ($data['submission_type'] === PollingUnitSubmission::TYPE_COORDINATES) {
            $pollingUnit = PollingUnit::with('ward.lga')->findOrFail($data['polling_unit_id']);
            $this->assertInState($observer, $pollingUnit->ward?->lga?->state_id, 'polling_unit_id');

            if ($pollingUnit->latitude !== null && $pollingUnit->longitude !== null) {
                $distance = Geo::distanceMeters(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    (float) $pollingUnit->latitude,
                    (float) $pollingUnit->longitude
                );
            }

            $duplicate = PollingUnitSubmission::query()
                ->where('tenant_id', $observer->tenant_id)
                ->where('submitted_by', $observer->id)
                ->where('status', PollingUnitSubmission::STATUS_PENDING)
                ->where('polling_unit_id', $pollingUnit->id)
                ->exists();

            if ($duplicate) {
                throw new ConflictHttpException('You already have a pending submission for this polling unit.');
            }
        } else {
            $ward = Ward::with('lga')->findOrFail($data['ward_id']);
            $this->assertInState($observer, $ward->lga?->state_id, 'ward_id');

            $duplicate = PollingUnitSubmission::query()
                ->where('tenant_id', $observer->tenant_id)
                ->where('submitted_by', $observer->id)
                ->where('status', PollingUnitSubmission::STATUS_PENDING)
                ->where('ward_id', $ward->id)
                ->where('proposed_name', $data['proposed_name'])
                ->exists();

            if ($duplicate) {
                throw new ConflictHttpException('You already have a pending submission for this polling unit.');
            }
        }

        /** @var list<string> $storedPaths */
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($observer, $data, $distance, $photos, &$storedPaths) {
                $submission = PollingUnitSubmission::create([
                    'uuid'                     => (string) Str::uuid(),
                    'tenant_id'                => $observer->tenant_id,
                    'submitted_by'             => $observer->id,
                    'submission_type'          => $data['submission_type'],
                    'polling_unit_id'          => $data['polling_unit_id'] ?? null,
                    'ward_id'                  => $data['ward_id'] ?? null,
                    'proposed_name'            => $data['proposed_name'] ?? null,
                    'latitude'                 => $data['latitude'],
                    'longitude'                => $data['longitude'],
                    'accuracy_m'               => $data['accuracy_m'],
                    'distance_from_existing_m' => $distance,
                    'captured_at'              => $data['captured_at'],
                    'status'                   => PollingUnitSubmission::STATUS_PENDING,
                ]);

                foreach ($photos as $file) {
                    $path = $this->storePhoto($observer->tenant_id, $submission->uuid, $file, $storedPaths);

                    PollingUnitSubmissionPhoto::create([
                        'tenant_id'     => $observer->tenant_id,
                        'submission_id' => $submission->id,
                        'storage_path'  => $path,
                        'mime_type'     => $file->getMimeType(),
                        'size_bytes'    => $file->getSize(),
                        'sha256'        => hash_file('sha256', $file->getRealPath()),
                    ]);
                }

                return $submission->load('photos');
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk(self::PHOTO_DISK)->delete($path);
            }
            throw $e;
        }
    }

    private function storePhoto(int $tenantId, string $submissionUuid, UploadedFile $file, array &$storedPaths): string
    {
        $extension = self::PHOTO_EXTENSIONS[$file->getMimeType()] ?? 'bin';
        $directory = "polling-unit-evidence/{$tenantId}/{$submissionUuid}";

        $path = Storage::disk(self::PHOTO_DISK)->putFileAs($directory, $file, Str::uuid() . '.' . $extension);
        $storedPaths[] = $path;

        return $path;
    }

    /**
     * @param  string|null  $puCode  Required when approving a new_polling_unit submission.
     * @param  string|null  $name    Optional override of the observer's proposed name.
     */
    public function approve(PollingUnitSubmission $submission, User $reviewer, ?string $puCode, ?string $name): PollingUnitSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $puCode, $name) {
            $locked = PollingUnitSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $this->assertPending($locked);

            if ($locked->submission_type === PollingUnitSubmission::TYPE_COORDINATES) {
                PollingUnit::whereKey($locked->polling_unit_id)->update([
                    'latitude'  => $locked->latitude,
                    'longitude' => $locked->longitude,
                ]);
            } else {
                if ($puCode === null) {
                    throw ValidationException::withMessages([
                        'pu_code' => ['An official pu_code is required to approve a new polling unit.'],
                    ]);
                }

                if (PollingUnit::where('pu_code', $puCode)->exists()) {
                    throw ValidationException::withMessages([
                        'pu_code' => ['This pu_code already belongs to another polling unit.'],
                    ]);
                }

                $created = PollingUnit::create([
                    'ward_id'   => $locked->ward_id,
                    'pu_code'   => $puCode,
                    'name'      => $name ?? $locked->proposed_name,
                    'latitude'  => $locked->latitude,
                    'longitude' => $locked->longitude,
                ]);

                $locked->polling_unit_id = $created->id;
            }

            $locked->status = PollingUnitSubmission::STATUS_APPROVED;
            $locked->reviewed_by = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->save();

            $this->notifySubmitter($locked, $reviewer, 'approved', $name ?? $locked->proposed_name);

            return $locked;
        });
    }

    public function reject(PollingUnitSubmission $submission, User $reviewer, string $note): PollingUnitSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $note) {
            $locked = PollingUnitSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $this->assertPending($locked);

            $locked->status = PollingUnitSubmission::STATUS_REJECTED;
            $locked->reviewed_by = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->review_note = $note;
            $locked->save();

            $this->notifySubmitter($locked, $reviewer, 'rejected', $note);

            return $locked;
        });
    }

    private function notifySubmitter(PollingUnitSubmission $submission, User $reviewer, string $outcome, ?string $detail): void
    {
        $submitter = User::find($submission->submitted_by);
        if ($submitter === null) {
            return;
        }

        $place = $submission->submission_type === PollingUnitSubmission::TYPE_NEW
            ? ($detail ?? $submission->proposed_name)
            : ($submission->pollingUnit?->name ?? 'your polling unit');

        app(InboxService::class)->notify(
            $submitter,
            $outcome === 'approved' ? 'Polling unit location approved' : 'Polling unit location needs changes',
            $outcome === 'approved'
                ? "{$place} has been approved by {$reviewer->name}."
                : "{$place} was rejected by {$reviewer->name}: {$detail}",
            ['type' => 'polling_unit_' . $outcome, 'submission_id' => $submission->id],
            $outcome === 'rejected' ? 'high' : 'normal'
        );
    }

    private function assertPending(PollingUnitSubmission $submission): void
    {
        if ($submission->status !== PollingUnitSubmission::STATUS_PENDING) {
            throw new ConflictHttpException("This submission has already been {$submission->status}.");
        }
    }

    private function assertInState(User $observer, ?int $stateId, string $field): void
    {
        if ($observer->state_id !== null && $stateId !== $observer->state_id) {
            throw ValidationException::withMessages([
                $field => ['This location is outside your assigned state.'],
            ]);
        }
    }
}
