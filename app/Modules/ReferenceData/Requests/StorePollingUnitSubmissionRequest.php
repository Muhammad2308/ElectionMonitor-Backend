<?php

namespace App\Modules\ReferenceData\Requests;

use App\Modules\ReferenceData\Models\PollingUnitSubmission;
use Illuminate\Foundation\Http\FormRequest;

class StorePollingUnitSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('polling-units.submit');
    }

    public function rules(): array
    {
        return [
            'submission_type' => ['required', 'in:' . PollingUnitSubmission::TYPE_COORDINATES . ',' . PollingUnitSubmission::TYPE_NEW],
            'polling_unit_id' => ['required_if:submission_type,' . PollingUnitSubmission::TYPE_COORDINATES, 'integer', 'exists:polling_units,id'],
            'ward_id'         => ['required_if:submission_type,' . PollingUnitSubmission::TYPE_NEW, 'integer', 'exists:wards,id'],
            'proposed_name'   => ['required_if:submission_type,' . PollingUnitSubmission::TYPE_NEW, 'string', 'max:255'],

            // Nigeria's approximate bounding box. Rejects fixes from a device
            // that is clearly outside the country (e.g. default or spoofed location).
            'latitude'        => ['required', 'numeric', 'between:4.2,13.9'],
            'longitude'       => ['required', 'numeric', 'between:2.6,14.7'],

            // Fixes worse than 100m are too imprecise to place a polling unit.
            'accuracy_m'      => ['required', 'numeric', 'min:0', 'max:100'],
            'captured_at'     => ['required', 'date'],

            // Evidence: at least one photo of the polling point, at most three.
            'photos'          => ['required', 'array', 'min:1', 'max:3'],
            'photos.*'        => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ];
    }
}
