<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\EmailTemplateType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property EmailTemplateType $type
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'name', 'subject', 'body', 'is_default'])]
class EmailTemplate extends Model
{
    use BelongsToTeam;

    /**
     * Render the subject and body with the given placeholder values.
     *
     * @param  array<string, string|int|float|null>  $values  e.g. ['{invoice_number}' => 'INV-2627-0001']
     * @return array{subject: string, body: string}
     */
    public function render(array $values): array
    {
        $replacements = array_map(fn ($value) => (string) $value, $values);

        return [
            'subject' => strtr($this->subject, $replacements),
            'body' => strtr($this->body, $replacements),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmailTemplateType::class,
            'is_default' => 'boolean',
        ];
    }
}
