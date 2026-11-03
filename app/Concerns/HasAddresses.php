<?php

namespace App\Concerns;

use App\Enums\AddressType;
use App\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAddresses
{
    /**
     * Get the addresses of the record.
     *
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')->orderBy('id');
    }

    /**
     * Get the address of the given type, falling back to the primary address.
     */
    public function addressFor(AddressType $type = AddressType::Primary): ?Address
    {
        $addresses = $this->relationLoaded('addresses') ? $this->getRelation('addresses') : $this->addresses()->get();

        return $addresses->firstWhere('type', $type)
            ?? $addresses->firstWhere('type', AddressType::Primary)
            ?? $addresses->first();
    }

    /**
     * Replace the record's addresses with the given list.
     *
     * @param  array<int, array<string, mixed>>|null  $addresses
     */
    public function syncAddresses(?array $addresses): void
    {
        $this->addresses()->delete();

        foreach ($addresses ?? [] as $address) {
            if (blank($address['line_1'] ?? null) && blank($address['city'] ?? null)) {
                continue;
            }

            $this->addresses()->create([
                'team_id' => $this->getAttribute('team_id'),
                'type' => $address['type'] ?? AddressType::Primary->value,
                'line_1' => $address['line_1'] ?? null,
                'line_2' => $address['line_2'] ?? null,
                'city' => $address['city'] ?? null,
                'province' => $address['province'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'] ?? 'Pakistan',
            ]);
        }
    }
}
