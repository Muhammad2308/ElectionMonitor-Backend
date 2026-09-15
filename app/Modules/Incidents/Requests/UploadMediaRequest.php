<?php

namespace App\Modules\Incidents\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('incidents.create');
    }

    public function rules(): array
    {
        return [
            'incident_id' => ['required', 'uuid', 'exists:incidents,id'],
            'file'        => [
                'required',
                'file',
                'max:20480', // 20 MB
                'mimes:jpeg,jpg,png,webp,mp4,mov,mp3,aac,ogg',
            ],
            'type'        => ['nullable', 'in:image,audio,video'],
        ];
    }
}
