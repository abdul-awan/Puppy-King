<?php

namespace App\Support;

use App\Models\Department;
use App\Models\PaymentTerm;
use App\Models\Tag;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WorkType;

/**
 * Option lists for select inputs, limited to the current team.
 */
final class TeamOptions
{
    /**
     * Get the current team's members.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function users(): array
    {
        $team = app(CurrentTeam::class)->team();

        return $team->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    /**
     * Get the current team's tags.
     *
     * @return array<int, array{id: int, name: string, color: string}>
     */
    public static function tags(): array
    {
        return Tag::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])
            ->all();
    }

    /**
     * Get the current team's payment terms.
     *
     * @return array<int, array{id: int, name: string, days: int, end_of_month: bool, is_default: bool}>
     */
    public static function paymentTerms(): array
    {
        return PaymentTerm::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentTerm $term) => [
                'id' => $term->id,
                'name' => $term->name,
                'days' => $term->days,
                'end_of_month' => $term->end_of_month,
                'is_default' => $term->is_default,
            ])
            ->all();
    }

    /**
     * Get the current team's active tax rates.
     *
     * @return array<int, array{id: int, name: string, rate: string, is_default: bool}>
     */
    public static function taxRates(): array
    {
        return TaxRate::query()
            ->active()
            ->get()
            ->map(fn (TaxRate $rate) => [
                'id' => $rate->id,
                'name' => $rate->name,
                'rate' => $rate->rate,
                'is_default' => $rate->is_default,
            ])
            ->all();
    }

    /**
     * Get the current team's active work types.
     *
     * @return array<int, array{id: int, name: string, hourly_rate: string|null, is_billable: bool}>
     */
    public static function workTypes(): array
    {
        return WorkType::query()
            ->active()
            ->get()
            ->map(fn (WorkType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'hourly_rate' => $type->hourly_rate,
                'is_billable' => $type->is_billable,
            ])
            ->all();
    }

    /**
     * Get the current team's departments.
     *
     * @return array<int, array{id: int, name: string, is_default: bool}>
     */
    public static function departments(): array
    {
        return Department::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'is_default' => $department->is_default,
            ])
            ->all();
    }

    /**
     * Get the currencies offered on documents.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function currencies(): array
    {
        return array_map(fn (string $code, string $name) => ['value' => $code, 'label' => "{$code} - {$name}"], array_keys(self::CURRENCIES), self::CURRENCIES);
    }

    /**
     * Supported currencies.
     *
     * @var array<string, string>
     */
    public const CURRENCIES = [
        'PKR' => 'Pakistani rupee',
        'USD' => 'US dollar',
        'EUR' => 'Euro',
        'GBP' => 'British pound',
        'AED' => 'UAE dirham',
        'SAR' => 'Saudi riyal',
        'CNY' => 'Chinese yuan',
        'CAD' => 'Canadian dollar',
        'AUD' => 'Australian dollar',
    ];
}
