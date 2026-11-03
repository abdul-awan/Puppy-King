<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The income tax rates for salaried individuals of one tax year (1 July to 30 June; tax year 2026
 * ends on 30 June 2026). Each slab reads: where the taxable income exceeds "from", the tax is
 * "fixed" plus "rate"% of the amount exceeding "from". A surcharge (a percentage of the tax) can
 * apply above a threshold. The rates change with every budget, so each company can edit them.
 *
 * @property int $id
 * @property int $team_id
 * @property int $tax_year
 * @property string $name
 * @property list<array{from: string, fixed: string, rate: string}> $slabs
 * @property string|null $surcharge_threshold
 * @property string $surcharge_percent
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['tax_year', 'name', 'slabs', 'surcharge_threshold', 'surcharge_percent', 'notes'])]
class SalaryTaxTable extends Model
{
    use BelongsToTeam;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'surcharge_percent' => '0.00',
    ];

    /**
     * Get a team's table for the tax year, or else its latest earlier one (or else its earliest one).
     */
    public static function forTaxYear(int $teamId, int $taxYear): ?self
    {
        $tables = fn () => self::query()->withoutGlobalScopes()->where('team_id', $teamId);

        return $tables()->where('tax_year', '<=', $taxYear)->orderByDesc('tax_year')->first()
            ?? $tables()->orderBy('tax_year')->first();
    }

    /**
     * Get the tax on a year's taxable income.
     */
    public function annualTax(BigDecimal $income): BigDecimal
    {
        $tax = BigDecimal::zero();

        if (! $income->isPositive()) {
            return $tax->toScale(2);
        }

        // The slabs are sorted from low to high: the last one the income exceeds applies.
        foreach ($this->sortedSlabs() as $slab) {
            $from = BigDecimal::of($slab['from']);

            if ($income->isGreaterThan($from)) {
                $tax = BigDecimal::of($slab['fixed'])->plus(
                    $income->minus($from)->multipliedBy(BigDecimal::of($slab['rate']))->dividedBy(100, 2, RoundingMode::HalfUp),
                );
            }
        }

        if ($this->surcharge_threshold !== null && $income->isGreaterThan(BigDecimal::of($this->surcharge_threshold))) {
            $tax = $tax->plus($tax->multipliedBy(BigDecimal::of($this->surcharge_percent))->dividedBy(100, 2, RoundingMode::HalfUp));
        }

        return $tax->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Get the slabs from the lowest to the highest.
     *
     * @return list<array{from: string, fixed: string, rate: string}>
     */
    public function sortedSlabs(): array
    {
        $slabs = $this->slabs;

        usort($slabs, fn (array $a, array $b) => BigDecimal::of($a['from'])->compareTo(BigDecimal::of($b['from'])));

        return $slabs;
    }

    /**
     * Get the label of the tax year, e.g. "Tax year 2027 (July 2026 – June 2027)".
     */
    public function label(): string
    {
        return sprintf('Tax year %d (July %d – June %d)', $this->tax_year, $this->tax_year - 1, $this->tax_year);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'slabs' => 'array',
            'surcharge_threshold' => 'decimal:2',
            'surcharge_percent' => 'decimal:2',
        ];
    }
}
