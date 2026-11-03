<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\Province;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A legal entity or branch of a team: its details appear on documents and it
 * keeps its own document numbering.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $ntn
 * @property string|null $strn
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property Province|null $province
 * @property string|null $postal_code
 * @property string $country
 * @property string|null $bank_name
 * @property string|null $bank_account_title
 * @property string|null $bank_account_number
 * @property string|null $iban
 * @property string|null $logo_path
 * @property string|null $document_footer
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'legal_name', 'ntn', 'strn', 'email', 'phone', 'website',
    'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country',
    'bank_name', 'bank_account_title', 'bank_account_number', 'iban',
    'logo_path', 'document_footer', 'is_default',
])]
class Department extends Model
{
    use BelongsToTeam;

    /**
     * Get the name printed on documents.
     */
    public function displayName(): string
    {
        return $this->legal_name ?: $this->name;
    }

    /**
     * Get the address as printable lines.
     *
     * @return array<int, string>
     */
    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->address_line_1,
            $this->address_line_2,
            trim(implode(' ', array_filter([$this->city, $this->postal_code]))),
            $this->province?->label(),
            $this->country,
        ]));
    }

    /**
     * Get the logo as a data URI for embedding in PDFs.
     */
    public function logoDataUri(): ?string
    {
        $disk = Storage::disk('local');

        if (! $this->logo_path || ! $disk->exists($this->logo_path)) {
            return null;
        }

        $mime = $disk->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($this->logo_path));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'province' => Province::class,
            'is_default' => 'boolean',
        ];
    }
}
