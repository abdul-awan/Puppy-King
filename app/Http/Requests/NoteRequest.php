<?php

namespace App\Http\Requests;

use App\Support\RecordTypes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NoteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'notable_type' => [$creating ? 'required' : 'prohibited', 'string', RecordTypes::rule()],
            'notable_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'body' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['boolean'],
        ];
    }
}
