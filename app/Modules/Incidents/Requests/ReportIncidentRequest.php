<?php

namespace App\Modules\Incidents\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('incidents.create');
    }

    public function rules(): array
    {
        return [
            'id'              => ['nullable', 'uuid'],
            'category_id'     => ['required', 'integer', 'exists:incident_categories,id'],
            'polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
            'severity'        => ['nullable', 'in:low,medium,high,critical'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'incident_time'   => ['nullable', 'date'],
            'latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'       => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
