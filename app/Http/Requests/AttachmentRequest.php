<?php

namespace App\Http\Requests;

use App\Support\RecordTypes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'string', RecordTypes::rule()],
            'attachable_id' => ['required', 'integer'],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.max' => 'Each file may be at most 20 MB.',
        ];
    }
}
