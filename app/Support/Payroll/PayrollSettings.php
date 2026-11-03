<?php

namespace App\Support\Payroll;

use App\Models\Team;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * A company's payroll settings (stored in the team settings under "payroll").
 */
final class PayrollSettings
{
    /**
     * The default settings. EOBI contributions are a percentage of the minimum wage set by the
     * government (Rs 37,000 a month when this was written: 5% by the employer, 1% by the employee).
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        // How the salary of part of a month is worked out: per day of the month or per 1/30.
        'days_basis' => 'calendar',
        'whole_rupees' => true,
        // 0 = the last day of the month; 1-28 = that day of the next month.
        'pay_day' => 0,
        'eobi_default' => false,
        'eobi_wage_base' => '37000',
        'eobi_employer_percent' => '5',
        'eobi_employee_percent' => '1',
        'show_employer_contributions' => false,
        'payslip_note' => '',
    ];

    /**
     * The ways to work out the salary of part of a month.
     *
     * @var array<string, string>
     */
    public const DAYS_BASES = [
        'calendar' => 'Days in the month (28 to 31)',
        'thirty' => 'Always 30 days',
    ];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(private array $values)
    {
        //
    }

    /**
     * Get the payroll settings of a team.
     */
    public static function for(Team $team): self
    {
        $stored = $team->teamSettings()->get('payroll', []);

        return new self(array_merge(self::DEFAULTS, is_array($stored) ? $stored : []));
    }

    /**
     * Get the way the salary of part of a month is worked out ("calendar" or "thirty").
     */
    public function daysBasis(): string
    {
        $basis = (string) $this->values['days_basis'];

        return array_key_exists($basis, self::DAYS_BASES) ? $basis : 'calendar';
    }

    /**
     * Determine if the amounts are rounded to whole rupees.
     */
    public function wholeRupees(): bool
    {
        return (bool) $this->values['whole_rupees'];
    }

    /**
     * Get the number of decimals of the worked out amounts.
     *
     * @return 0|2
     */
    public function scale(): int
    {
        return $this->wholeRupees() ? 0 : 2;
    }

    /**
     * Round a worked out amount (to whole rupees or to paisa).
     */
    public function round(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale($this->scale(), RoundingMode::HalfUp)->toScale(2);
    }

    /**
     * Get the pay day (0 = the last day of the month, 1-28 = that day of the next month).
     */
    public function payDay(): int
    {
        return max(0, min(28, (int) $this->values['pay_day']));
    }

    /**
     * Get the day the salary of a month is paid.
     */
    public function payDateFor(CarbonImmutable $month): CarbonImmutable
    {
        return $this->payDay() === 0
            ? $month->endOfMonth()->startOfDay()
            : $month->startOfMonth()->addMonthNoOverflow()->setDay($this->payDay());
    }

    /**
     * Determine if new employees pay EOBI contributions by default.
     */
    public function eobiDefault(): bool
    {
        return (bool) $this->values['eobi_default'];
    }

    /**
     * Get the monthly EOBI contribution of the employee.
     */
    public function eobiEmployeeShare(): BigDecimal
    {
        return $this->eobiShare('eobi_employee_percent');
    }

    /**
     * Get the monthly EOBI contribution of the employer.
     */
    public function eobiEmployerShare(): BigDecimal
    {
        return $this->eobiShare('eobi_employer_percent');
    }

    /**
     * Determine if the employer contributions are printed on payslips.
     */
    public function showEmployerContributions(): bool
    {
        return (bool) $this->values['show_employer_contributions'];
    }

    /**
     * Get the note printed at the bottom of payslips.
     */
    public function payslipNote(): ?string
    {
        $note = trim((string) $this->values['payslip_note']);

        return $note === '' ? null : $note;
    }

    /**
     * Get all settings.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'days_basis' => $this->daysBasis(),
            'whole_rupees' => $this->wholeRupees(),
            'pay_day' => $this->payDay(),
            'eobi_default' => $this->eobiDefault(),
            'eobi_wage_base' => (string) $this->decimal('eobi_wage_base'),
            'eobi_employer_percent' => (string) $this->decimal('eobi_employer_percent'),
            'eobi_employee_percent' => (string) $this->decimal('eobi_employee_percent'),
            'eobi_employer_share' => (string) $this->eobiEmployerShare(),
            'eobi_employee_share' => (string) $this->eobiEmployeeShare(),
            'show_employer_contributions' => $this->showEmployerContributions(),
            'payslip_note' => $this->payslipNote() ?? '',
        ];
    }

    /**
     * Work out an EOBI contribution: a percentage of the wage it is based on.
     */
    private function eobiShare(string $percentKey): BigDecimal
    {
        return $this->round($this->decimal('eobi_wage_base')->multipliedBy($this->decimal($percentKey))->dividedBy(100, 4, RoundingMode::HalfUp));
    }

    /**
     * Read a stored number (a broken value counts as zero).
     */
    private function decimal(string $key): BigDecimal
    {
        $value = $this->values[$key] ?? '0';

        return is_numeric($value) ? BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp) : BigDecimal::zero()->toScale(2);
    }
}
