<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PayAmountType;
use App\Enums\PayComponentKind;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An allowance, deduction or employer contribution that can be put on an employee's salary,
 * e.g. house rent allowance, medical allowance or the provident fund.
 *
 * An earning is taxable, tax-free, or tax-free up to a percentage of the basic salary (the medical
 * allowance is tax-free up to 10% of the basic salary in Pakistan).
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property PayComponentKind $kind
 * @property PayAmountType $amount_type
 * @property string $default_value
 * @property bool $taxable
 * @property string|null $tax_free_percent
 * @property bool $is_active
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'kind', 'amount_type', 'default_value', 'taxable', 'tax_free_percent', 'is_active', 'sort'])]
class PayComponent extends Model
{
    use BelongsToTeam;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount_type' => 'fixed',
        'default_value' => '0.00',
        'taxable' => true,
        'is_active' => true,
        'sort' => 0,
    ];

    /**
     * Get the salaries the component is on.
     *
     * @return HasMany<EmployeePayItem, $this>
     */
    public function payItems(): HasMany
    {
        return $this->hasMany(EmployeePayItem::class);
    }

    /**
     * Get the part of an earning that counts as taxable income.
     */
    public function taxablePart(BigDecimal $amount, BigDecimal $basic): BigDecimal
    {
        if ($this->kind !== PayComponentKind::Earning || ! $this->taxable) {
            return BigDecimal::zero();
        }

        $freePercent = BigDecimal::of($this->tax_free_percent ?? '0');

        if (! $freePercent->isPositive()) {
            return $amount;
        }

        $taxable = $amount->minus($basic->multipliedBy($freePercent)->dividedBy(100, 2, RoundingMode::HalfUp));

        return $taxable->isNegative() ? BigDecimal::zero()->toScale(2) : $taxable;
    }

    /**
     * Describe how the component is taxed (earnings only).
     */
    public function taxLabel(): ?string
    {
        if ($this->kind !== PayComponentKind::Earning) {
            return null;
        }

        if (! $this->taxable) {
            return 'Tax-free';
        }

        $freePercent = BigDecimal::of($this->tax_free_percent ?? '0');

        return $freePercent->isPositive()
            ? 'Tax-free up to '.rtrim(rtrim((string) $freePercent, '0'), '.').'% of basic'
            : 'Taxable';
    }

    /**
     * Scope a query to components that can still be put on salaries.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PayComponentKind::class,
            'amount_type' => PayAmountType::class,
            'default_value' => 'decimal:2',
            'taxable' => 'boolean',
            'tax_free_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
