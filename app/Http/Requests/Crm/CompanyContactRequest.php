<?php

namespace App\Http\Requests\Crm;

use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyContactRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', TeamRule::exists('contacts', softDeletes: true)],
            'position' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
            'is_decision_maker' => ['boolean'],
        ];
    }
}
