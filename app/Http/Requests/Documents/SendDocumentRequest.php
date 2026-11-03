<?php

namespace App\Http\Requests\Documents;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:1000'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'attach_pdf' => ['boolean'],
        ];
    }

    /**
     * Check every address in the "to" and "cc" lists.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (['to', 'cc'] as $field) {
                $addresses = array_filter(array_map('trim', preg_split('/[,;]/', (string) $this->input($field)) ?: []));

                foreach ($addresses as $address) {
                    if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                        $validator->errors()->add($field, "\"{$address}\" is not a valid e-mail address.");
                    }
                }
            }
        }];
    }

    /**
     * Get the e-mail to send.
     *
     * @return array{to: string, cc: string|null, subject: string, body: string, attach_pdf: bool}
     */
    public function message(): array
    {
        return [
            'to' => $this->string('to')->toString(),
            'cc' => $this->filled('cc') ? $this->string('cc')->toString() : null,
            'subject' => $this->string('subject')->toString(),
            'body' => $this->string('body')->toString(),
            'attach_pdf' => $this->boolean('attach_pdf', true),
        ];
    }
}
