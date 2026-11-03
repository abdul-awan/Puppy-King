<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PayComponentKind;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee's pay for one month. The employee's details (name, designation, bank account) are
 * kept as they were, so an approved payslip never changes when the employee is changed later.
 *
 * @property int $id
 * @property int $team_id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string|null $number
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property string $employee_name
 * @property string $employee_code
 * @property string|null $designation
 * @property string|null $department
 * @property string|null $cnic
 * @property PaymentMethod $payment_method
 * @property string|null $bank_name
 * @property string|null $bank_account_title
 * @property string|null $bank_account_number
 * @property int $days_in_period
 * @property int $employed_days
 * @property string $unpaid_days
 * @property string $payable_days
 * @property string $basic_salary
 * @property string $gross
 * @property string $taxable_income
 * @property string $income_tax
 * @property string|null $tax_override
 * @property array<string, mixed>|null $tax_details
 * @property string $deductions
 * @property string $employer_contributions
 * @property string $net
 * @property CarbonImmutable|null $paid_on
 * @property string|null $payment_reference
 * @property CarbonImmutable|null $emailed_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 * @property-read Collection<int, PayslipLine> $lines
 */
#[Fillable(['payroll_run_id', 'employee_id', 'period_start', 'period_end', 'unpaid_days', 'tax_override', 'notes'])]
class Payslip extends Model
{
    use BelongsToTeam;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unpaid_days' => '0.00',
        'payable_days' => '0.00',
        'basic_salary' => '0.00',
        'gross' => '0.00',
        'taxable_income' => '0.00',
        'income_tax' => '0.00',
        'deductions' => '0.00',
        'employer_contributions' => '0.00',
        'net' => '0.00',
    ];

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * Get the earnings, deductions and employer contributions, in their order on the payslip.
     *
     * @return HasMany<PayslipLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Get the lines of one kind.
     *
     * @return Collection<int, PayslipLine>
     */
    public function linesOf(PayComponentKind $kind): Collection
    {
        return $this->lines->filter(fn (PayslipLine $line) => $line->kind === $kind)->values();
    }

    /**
     * Get the name of the month, e.g. "October 2026".
     */
    public function periodLabel(): string
    {
        return $this->period_start->format('F Y');
    }

    /**
     * Determine if the salary was paid.
     */
    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payment_method' => PaymentMethod::class,
            'days_in_period' => 'integer',
            'employed_days' => 'integer',
            'unpaid_days' => 'decimal:2',
            'payable_days' => 'decimal:2',
            'basic_salary' => 'decimal:2',
            'gross' => 'decimal:2',
            'taxable_income' => 'decimal:2',
            'income_tax' => 'decimal:2',
            'tax_override' => 'decimal:2',
            'tax_details' => 'array',
            'deductions' => 'decimal:2',
            'employer_contributions' => 'decimal:2',
            'net' => 'decimal:2',
            'paid_on' => 'date',
            'emailed_at' => 'datetime',
        ];
    }
}
