<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Models\Product;
use App\Support\CurrentTeam;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $unique = Rule::unique('products', 'code')->where('team_id', app(CurrentTeam::class)->id());

        return [
            'category_id' => ['nullable', 'integer', TeamRule::exists('product_categories')],
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', $product instanceof Product ? $unique->ignore($product->id) : $unique],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'string', 'max:20'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'tax_rate_id' => ['nullable', 'integer', TeamRule::exists('tax_rates')],
            'hs_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9.]+$/'],
            'track_stock' => ['boolean'],
            'stock_quantity' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'is_active' => ['boolean'],
            ...Product::customFieldRules(),
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
            'code.unique' => 'Another product already uses this code.',
            'hs_code.regex' => 'The HS code may only contain digits and dots, e.g. 8471.3010.',
        ];
    }
}
