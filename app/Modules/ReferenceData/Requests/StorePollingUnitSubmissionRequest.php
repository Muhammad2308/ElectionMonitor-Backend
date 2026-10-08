<?php

namespace App\Modules\ReferenceData\Requests;

use App\Modules\ReferenceData\Models\PollingUnitSubmission;
use Illuminate\Foundation\Http\FormRequest;

class StorePollingUnitSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user->can('polling-units.submit')
            || $user->can('polling-units.review')
            || in_array($user->role_type, ['cybernet_superadmin', 'national_master_admin', 'state_master_admin', 'state_admin', 'observer'])
            || $user->status === 'active';
    }

    public function rules(): array
    {
        return [
            'submission_type' => ['required', 'in:' . PollingUnitSubmission::TYPE_COORDINATES . ',' . PollingUnitSubmission::TYPE_NEW],
            'polling_unit_id' => ['required_if:submission_type,' . PollingUnitSubmission::TYPE_COORDINATES, 'integer', 'exists:polling_units,id'],
            'ward_id'         => ['required_if:submission_type,' . PollingUnitSubmission::TYPE_NEW, 'integer', 'exists:wards,id'],
            'proposed_name'   => ['required_if:submission_type,' . PollingUnitSubmission::TYPE_NEW, 'string', 'max:255'],

            'latitude'        => ['required', 'numeric', 'between:-90,90'],
            'longitude'       => ['required', 'numeric', 'between:-180,180'],

            // Allow up to 1,000,000m accuracy for coarse/desktop testing and remote cell triangulation
            'accuracy_m'      => ['required', 'numeric', 'min:0', 'max:1000000'],
            'captured_at'     => ['required', 'date'],

            // Evidence: at least one photo of the polling point, at most three.
            'photos'          => ['required', 'array', 'min:1', 'max:3'],
            'photos.*'        => ['file', 'mimes:jpeg,png,webp,jpg', 'max:10240'],
        ];
    }
}
