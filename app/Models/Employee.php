<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Enums\EmploymentType;
use App\Enums\PaymentMethod;
use App\Enums\SalaryTaxMode;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person on the company's payroll. They do not need an account; when they have one (as a member
 * of the company) they can see their own payslips.
 *
 * @property int $id
 * @property int $team_id
 * @property string $code
 * @property string $name
 * @property string|null $father_name
 * @property string|null $cnic
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $designation
 * @property string|null $department
 * @property EmploymentType $employment_type
 * @property CarbonImmutable $joining_date
 * @property CarbonImmutable|null $leaving_date
 * @property string $basic_salary
 * @property PaymentMethod $payment_method
 * @property string|null $bank_name
 * @property string|null $bank_account_title
 * @property string|null $bank_account_number
 * @property SalaryTaxMode $tax_mode
 * @property string|null $fixed_tax
 * @property bool $eobi
 * @property string|null $eobi_number
 * @property int|null $opening_tax_year
 * @property string|null $opening_taxable
 * @property string|null $opening_tax
 * @property int|null $user_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, EmployeePayItem> $payItems
 * @property-read Collection<int, EmployeeAdvance> $advances
 * @property-read Collection<int, Payslip> $payslips
 * @property-read User|null $user
 * @property-read User|null $creator
 */
#[Fillable([
    'code', 'name', 'father_name', 'cnic', 'date_of_birth', 'phone', 'email', 'address', 'designation', 'department',
    'employment_type', 'joining_date', 'leaving_date', 'basic_salary', 'payment_method', 'bank_name', 'bank_account_title',
    'bank_account_number', 'tax_mode', 'fixed_tax', 'eobi', 'eobi_number', 'opening_tax_year', 'opening_taxable', 'opening_tax',
    'user_id', 'notes', 'created_by',
])]
class Employee extends Model implements Attachable
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToTeam, HasAttachments, HasFactory, HasNotes, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'employment_type' => 'permanent',
        'payment_method' => 'bank_transfer',
        'tax_mode' => 'auto',
        'eobi' => false,
        'basic_salary' => '0.00',
    ];

    /**
     * Get the allowances, deductions and employer contributions on the salary.
     *
     * @return HasMany<EmployeePayItem, $this>
     */
    public function payItems(): HasMany
    {
        return $this->hasMany(EmployeePayItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Get the salary advances and loans.
     *
     * @return HasMany<EmployeeAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class)->orderByDesc('date')->orderByDesc('id');
    }

    /**
     * Get the payslips, newest first.
     *
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class)->orderByDesc('period_start');
    }

    /**
     * Get the employee's own login.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine if the employee works for the company at some point in the period.
     */
    public function isEmployedDuring(DateTimeInterface $start, DateTimeInterface $end): bool
    {
        return $this->joining_date->lessThanOrEqualTo($end)
            && ($this->leaving_date === null || $this->leaving_date->greaterThanOrEqualTo($start));
    }

    /**
     * Determine if the employee has left the company (before today).
     */
    public function hasLeft(): bool
    {
        return $this->leaving_date !== null && $this->leaving_date->lessThan(CarbonImmutable::today());
    }

    /**
     * Get the label and badge colour describing the employee's current state.
     *
     * @return array{label: string, color: string}
     */
    public function displayStatus(): array
    {
        return match (true) {
            $this->hasLeft() => ['label' => 'Left', 'color' => 'gray'],
            $this->joining_date->isFuture() => ['label' => 'Joins '.$this->joining_date->format('d M Y'), 'color' => 'blue'],
            $this->leaving_date !== null => ['label' => 'Leaving '.$this->leaving_date->format('d M Y'), 'color' => 'amber'],
            default => ['label' => 'Active', 'color' => 'green'],
        };
    }

    /**
     * Scope a query to the employees who work for the company at some point in the period.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEmployedDuring(Builder $query, DateTimeInterface $start, DateTimeInterface $end): void
    {
        $query->whereDate('joining_date', '<=', $end)
            ->where(fn (Builder $query) => $query->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', $start));
    }

    /**
     * Scope a query to the employees who have not left the company.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', CarbonImmutable::today()));
    }

    /**
     * Scope a query to employees matching a search term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhere('code', 'like', $like)
            ->orWhere('cnic', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('designation', 'like', $like)
            ->orWhere('department', 'like', $like));
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'leaving_date' => 'date',
            'employment_type' => EmploymentType::class,
            'basic_salary' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'tax_mode' => SalaryTaxMode::class,
            'fixed_tax' => 'decimal:2',
            'eobi' => 'boolean',
            'opening_tax_year' => 'integer',
            'opening_taxable' => 'decimal:2',
            'opening_tax' => 'decimal:2',
        ];
    }
}
