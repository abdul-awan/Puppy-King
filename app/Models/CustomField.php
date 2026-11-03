<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A team-defined extra field for companies, contacts, deals, products, projects or tickets.
 *
 * @property int $id
 * @property int $team_id
 * @property CustomFieldEntity $entity
 * @property string $label
 * @property string $key
 * @property CustomFieldType $type
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['entity', 'label', 'key', 'type', 'options', 'is_required', 'sort'])]
class CustomField extends Model
{
    use BelongsToTeam;

    /**
     * Get the validation rules for a value of this field.
     *
     * @return array<int, mixed>
     */
    public function validationRules(): array
    {
        return [
            $this->is_required ? 'required' : 'nullable',
            ...match ($this->type) {
                CustomFieldType::Text, CustomFieldType::Phone => ['string', 'max:255'],
                CustomFieldType::Textarea => ['string', 'max:5000'],
                CustomFieldType::Number => ['numeric'],
                CustomFieldType::Date => ['date'],
                CustomFieldType::Select => [Rule::in($this->options ?? [])],
                CustomFieldType::Checkbox => ['boolean'],
                CustomFieldType::Email => ['email', 'max:255'],
                CustomFieldType::Url => ['url', 'max:255'],
            },
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
            'entity' => CustomFieldEntity::class,
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
