<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AddressType;
use App\Enums\Province;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property AddressType $type
 * @property string|null $line_1
 * @property string|null $line_2
 * @property string|null $city
 * @property Province|null $province
 * @property string|null $postal_code
 * @property string $country
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'type', 'line_1', 'line_2', 'city', 'province', 'postal_code', 'country'])]
class Address extends Model
{
    use BelongsToTeam;

    /**
     * Get the record the address belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the address as printable lines.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->line_1,
            $this->line_2,
            trim(implode(' ', array_filter([$this->city, $this->postal_code]))),
            $this->province?->label(),
            $this->country !== 'Pakistan' ? $this->country : null,
        ]));
    }

    /**
     * Get the address on a single line.
     */
    public function singleLine(): string
    {
        return implode(', ', $this->lines());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'province' => Province::class,
        ];
    }
}
