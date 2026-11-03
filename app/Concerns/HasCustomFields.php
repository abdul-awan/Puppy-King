<?php

namespace App\Concerns;

use App\Enums\CustomFieldEntity;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Collection;

/**
 * Stores team-defined custom field values in the model's "custom_fields" JSON column.
 */
trait HasCustomFields
{
    /**
     * Get the custom field entity type of the model.
     */
    abstract public static function customFieldEntity(): CustomFieldEntity;

    /**
     * Initialize the trait: cast the JSON column.
     */
    public function initializeHasCustomFields(): void
    {
        $this->mergeCasts(['custom_fields' => 'array']);
    }

    /**
     * Get the validation rules for the model's custom fields in the current team.
     *
     * @return array<string, mixed>
     */
    public static function customFieldRules(): array
    {
        $rules = ['custom_fields' => ['nullable', 'array']];

        foreach (static::customFieldDefinitions() as $field) {
            $rules['custom_fields.'.$field->key] = $field->validationRules();
        }

        return $rules;
    }

    /**
     * Get the custom field definitions for the model in the current team.
     *
     * @return Collection<int, CustomField>
     */
    public static function customFieldDefinitions(): Collection
    {
        return CustomField::query()
            ->where('entity', static::customFieldEntity())
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }
}
