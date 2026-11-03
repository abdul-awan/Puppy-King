<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Models\Team;

/**
 * Read access to a team's settings, merged over the application defaults.
 */
final class TeamSettings
{
    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(private array $values)
    {
        //
    }

    /**
     * Get the settings for the given team.
     */
    public static function for(Team $team): self
    {
        return new self(self::merge(self::defaults(), $team->settings ?? []));
    }

    /**
     * Get the default settings for a new team.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $numbering = [];

        foreach (DocumentType::cases() as $type) {
            $numbering[$type->value] = ['pattern' => $type->defaultPattern(), 'padding' => 4];
        }

        return [
            'currency' => 'PKR',
            'fiscal_year_start_month' => 7,
            'further_tax_enabled' => true,
            'further_tax_rate' => '4.00',
            'quotation_validity_days' => 30,
            'reminders' => [
                'enabled' => false,
                'days_after_due' => [3, 10, 30],
            ],
            'daily_capacity_minutes' => 480,
            // ISO days of the week (1 = Monday … 7 = Sunday); Monday to Saturday is common in Pakistan.
            'working_days' => [1, 2, 3, 4, 5, 6],
            // The public support form (tickets); its secret address is made when it is switched on.
            'support' => [
                'enabled' => false,
                'token' => null,
            ],
            'numbering' => $numbering,
            // The template per kind of document ("classic" … or "custom:<id>"); what documents show is under
            // "documents.options" (see DocumentOptions, which fills in the defaults).
            'documents' => [
                'templates' => ['invoice' => 'classic', 'quotation' => 'classic', 'credit_note' => 'classic'],
            ],
        ];
    }

    /**
     * Get the working days (ISO numbers, 1 = Monday … 7 = Sunday).
     *
     * @return array<int, int>
     */
    public function workingDays(): array
    {
        $days = array_values(array_unique(array_map('intval', (array) $this->get('working_days', [1, 2, 3, 4, 5, 6]))));
        sort($days);

        return array_values(array_filter($days, fn (int $day) => $day >= 1 && $day <= 7));
    }

    /**
     * Get the minutes a person can work on a working day.
     */
    public function dailyCapacityMinutes(): int
    {
        return (int) $this->get('daily_capacity_minutes', 480);
    }

    /**
     * Get a setting using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->values, $key, $default);
    }

    /**
     * Get the team's default currency code.
     */
    public function currency(): string
    {
        return (string) $this->get('currency', 'PKR');
    }

    /**
     * Get the month (1-12) in which the fiscal year starts.
     */
    public function fiscalYearStartMonth(): int
    {
        return (int) $this->get('fiscal_year_start_month', 7);
    }

    /**
     * Get the further tax rate for sales to unregistered buyers, or null when disabled.
     */
    public function furtherTaxRate(): ?string
    {
        return $this->get('further_tax_enabled') ? (string) $this->get('further_tax_rate', '0') : null;
    }

    /**
     * Get the numbering format for the given document type.
     *
     * @return array{pattern: string, padding: int}
     */
    public function numbering(DocumentType $type): array
    {
        return [
            'pattern' => (string) $this->get("numbering.{$type->value}.pattern", $type->defaultPattern()),
            'padding' => (int) $this->get("numbering.{$type->value}.padding", 4),
        ];
    }

    /**
     * Get all settings.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Merge stored settings over defaults; lists are replaced as a whole.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($defaults[$key])) {
                $defaults[$key] = self::merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
