<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Enums\PayrollRunStatus;
use App\Support\Payroll\TaxYear;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The payroll of one month: a payslip for every employee. Draft payslips can be changed and are
 * worked out again when the salaries change; approving locks them and gives them their numbers.
 *
 * @property int $id
 * @property int $team_id
 * @property CarbonImmutable $period
 * @property CarbonImmutable|null $pay_date
 * @property PayrollRunStatus $status
 * @property int $employees_count
 * @property string $gross
 * @property string $deductions
 * @property string $income_tax
 * @property string $net
 * @property string $employer_cost
 * @property string|null $notes
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $paid_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Payslip> $payslips
 * @property-read User|null $approver
 * @property-read User|null $creator
 */
#[Fillable(['period', 'pay_date', 'notes', 'created_by'])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use BelongsToTeam, HasFactory, HasNotes, LogsActivity;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'employees_count' => 0,
        'gross' => '0.00',
        'deductions' => '0.00',
        'income_tax' => '0.00',
        'net' => '0.00',
        'employer_cost' => '0.00',
    ];

    /**
     * Get the payslips, by employee name.
     *
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class)->orderBy('employee_name')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the first day of the month.
     */
    public function periodStart(): CarbonImmutable
    {
        return $this->period->startOfMonth();
    }

    /**
     * Get the last day of the month.
     */
    public function periodEnd(): CarbonImmutable
    {
        return $this->period->endOfMonth()->startOfDay();
    }

    /**
     * Get the name of the month, e.g. "October 2026".
     */
    public function label(): string
    {
        return $this->period->format('F Y');
    }

    /**
     * Get the tax year the month belongs to (July to June).
     */
    public function taxYear(): int
    {
        return TaxYear::for($this->period);
    }

    /**
     * Determine if the payslips can still be changed.
     */
    public function isDraft(): bool
    {
        return $this->status === PayrollRunStatus::Draft;
    }

    /**
     * Get the label and badge colour describing the payroll's current state.
     *
     * @return array{label: string, color: string}
     */
    public function displayStatus(): array
    {
        if ($this->status === PayrollRunStatus::Approved && $this->payslips()->whereNotNull('paid_on')->exists()) {
            return ['label' => 'Partly paid', 'color' => 'amber'];
        }

        return ['label' => $this->status->label(), 'color' => $this->status->color()];
    }

    /**
     * Add up the payslips into the payroll's totals.
     */
    public function refreshTotals(): void
    {
        $payslips = $this->payslips()->get(['gross', 'deductions', 'income_tax', 'net', 'employer_contributions']);
        $sum = fn (string $field): string => (string) $payslips->reduce(
            fn (BigDecimal $total, Payslip $payslip) => $total->plus(BigDecimal::of($payslip->getAttribute($field))),
            BigDecimal::zero(),
        )->toScale(2);

        $this->forceFill([
            'employees_count' => $payslips->count(),
            'gross' => $sum('gross'),
            'deductions' => $sum('deductions'),
            'income_tax' => $sum('income_tax'),
            'net' => $sum('net'),
            'employer_cost' => $sum('employer_contributions'),
        ])->save();
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [
            'created_at', 'updated_at', 'status', 'employees_count', 'gross', 'deductions', 'income_tax', 'net',
            'employer_cost', 'approved_by', 'approved_at', 'paid_at',
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
            'period' => 'date',
            'pay_date' => 'date',
            'status' => PayrollRunStatus::class,
            'employees_count' => 'integer',
            'gross' => 'decimal:2',
            'deductions' => 'decimal:2',
            'income_tax' => 'decimal:2',
            'net' => 'decimal:2',
            'employer_cost' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
