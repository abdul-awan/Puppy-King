<?php

namespace App\Http\Requests\Invoicing;

use App\Http\Requests\Concerns\ValidatesDocumentLines;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreditNoteRequest extends FormRequest
{
    use ValidatesDocumentLines;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'restock' => ['boolean'],
            'introduction' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            ...$this->lineRules(),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->requireItemLine($validator)];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->lineMessages();
    }
}
