<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AddressType;
use App\Enums\Province;
use Illuminate\Validation\Rule;

trait ValidatesAddresses
{
    /**
     * Get the validation rules for an "addresses" list.
     *
     * @return array<string, mixed>
     */
    protected function addressRules(): array
    {
        return [
            'addresses' => ['nullable', 'array', 'max:10'],
            'addresses.*.type' => ['required', Rule::enum(AddressType::class)],
            'addresses.*.line_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
            'addresses.*.province' => ['nullable', Rule::enum(Province::class)],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.country' => ['nullable', 'string', 'max:100'],
        ];
    }
}
