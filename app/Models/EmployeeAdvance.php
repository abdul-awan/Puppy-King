<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PayrollRunStatus;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeAdvanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A salary advance or loan given to an employee. It is recovered through the payroll: every month
 * the installment is deducted from the salary until the whole amount is back.
 *
 * @property int $id
 * @property int $team_id
 * @property int $employee_id
 * @property CarbonImmutable $date
 * @property string $amount
 * @property string $installment
 * @property string|null $reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Employee $employee
 * @property-read User|null $creator
 */
#[Fillable(['employee_id', 'date', 'amount', 'installment', 'reason', 'created_by'])]
class EmployeeAdvance extends Model
{
    /** @use HasFactory<EmployeeAdvanceFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payslip lines that recover the advance.
     *
     * @return HasMany<PayslipLine, $this>
     */
    public function recoveries(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    /**
     * Get the amount recovered through approved payrolls (and draft payrolls when asked), leaving
     * out one payslip (the one being worked out).
     */
    public function recovered(bool $includeDrafts = false, ?int $exceptPayslipId = null): BigDecimal
    {
        $amounts = $this->recoveries()
            ->when($exceptPayslipId, fn (Builder $query, int $id) => $query->where('payslip_id', '!=', $id))
            ->when(! $includeDrafts, fn (Builder $query) => $query->whereHas(
                'payslip.run',
                fn (Builder $query) => $query->where('status', '!=', PayrollRunStatus::Draft),
            ))
            ->pluck('amount');

        return $amounts->reduce(
            fn (BigDecimal $sum, mixed $amount) => $sum->plus(BigDecimal::of((string) $amount)),
            BigDecimal::zero(),
        )->toScale(2);
    }

    /**
     * Get the amount still to be recovered after the approved payrolls.
     */
    public function outstanding(): BigDecimal
    {
        $outstanding = BigDecimal::of($this->amount)->minus($this->recovered());

        return $outstanding->isNegative() ? BigDecimal::zero()->toScale(2) : $outstanding;
    }

    /**
     * Get the amount to recover on a payslip: the installment, at most what is left after the
     * other payslips (draft ones included, so two open payrolls never recover the same money).
     */
    public function dueOn(?int $payslipId = null): BigDecimal
    {
        $left = BigDecimal::of($this->amount)->minus($this->recovered(includeDrafts: true, exceptPayslipId: $payslipId));
        $installment = BigDecimal::of($this->installment);

        if (! $left->isPositive()) {
            return BigDecimal::zero()->toScale(2);
        }

        return $installment->isGreaterThan($left) ? $left : $installment;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'installment' => 'decimal:2',
        ];
    }
}
