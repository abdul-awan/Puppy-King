<?php

namespace App\Http\Requests\Projects;

use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectMaterialRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', TeamRule::exists('products')],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'tax_rate_id' => ['nullable', 'integer', TeamRule::exists('tax_rates')],
            'is_billable' => ['boolean'],
        ];
    }
}
