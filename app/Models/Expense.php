<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Contracts\Summarizable;
use App\Enums\ExpenseStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bill or receipt from a supplier.
 *
 * The input sales tax on it can be claimed in the sales tax return when the supplier is
 * registered. As withholding agent the team may deduct income tax and part of the sales
 * tax when paying; what the supplier receives is the total minus those withholdings.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $number
 * @property string $title
 * @property int|null $company_id
 * @property string|null $supplier_name
 * @property string|null $supplier_ntn
 * @property string|null $supplier_strn
 * @property string|null $bill_number
 * @property int|null $category_id
 * @property int|null $project_id
 * @property CarbonImmutable $date
 * @property CarbonImmutable|null $due_date
 * @property string $currency
 * @property string $exchange_rate
 * @property string $net_amount
 * @property int|null $tax_rate_id
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $total
 * @property bool $tax_claimable
 * @property string $income_tax_withheld
 * @property string $sales_tax_withheld
 * @property string $amount_paid
 * @property ExpenseStatus $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $paid_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Company|null $supplier
 * @property-read ExpenseCategory|null $category
 * @property-read TaxRate|null $taxRate
 * @property-read User|null $approver
 * @property-read User|null $creator
 * @property-read Collection<int, ExpensePayment> $payments
 */
#[Fillable([
    'title', 'company_id', 'supplier_name', 'supplier_ntn', 'supplier_strn', 'bill_number', 'category_id', 'project_id', 'date',
    'due_date', 'currency', 'exchange_rate', 'net_amount', 'tax_rate_id', 'tax_amount', 'tax_claimable',
    'income_tax_withheld', 'sales_tax_withheld', 'notes', 'created_by',
])]
class Expense extends Model implements Attachable, Summarizable
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToTeam, HasAttachments, HasFactory, HasNotes, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'tax_rate' => '0',
        'tax_amount' => '0.00',
        'income_tax_withheld' => '0.00',
        'sales_tax_withheld' => '0.00',
        'amount_paid' => '0.00',
        'tax_claimable' => true,
    ];

    /**
     * Get the supplier (a company in the CRM).
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id')->withTrashed();
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * Get the project the cost belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
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
     * @return HasMany<ExpensePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->orderBy('date')->orderBy('id');
    }

    /**
     * Take the tax rate's percentage (the tax amount itself may be corrected by hand)
     * and work out the total.
     */
    public function applyAmounts(?TaxRate $rate, ?string $taxAmount = null): void
    {
        $net = BigDecimal::of($this->net_amount ?: '0');

        $this->tax_rate_id = $rate?->id;
        $this->tax_rate = $rate !== null ? (string) $rate->rate : '0';
        $this->tax_amount = $taxAmount !== null && $taxAmount !== ''
            ? (string) BigDecimal::of($taxAmount)->toScale(2, RoundingMode::HalfUp)
            : (string) $net->multipliedBy(BigDecimal::of($this->tax_rate))->dividedBy(100, 2, RoundingMode::HalfUp);
        $this->total = (string) $net->plus(BigDecimal::of($this->tax_amount))->toScale(2);
    }

    /**
     * Get the amount the supplier receives: the total minus the tax withheld by the team.
     */
    public function payable(): string
    {
        return (string) BigDecimal::of($this->total)
            ->minus(BigDecimal::of($this->income_tax_withheld))
            ->minus(BigDecimal::of($this->sales_tax_withheld))
            ->toScale(2);
    }

    /**
     * Get the amount still to be paid to the supplier.
     */
    public function balanceDue(): string
    {
        return (string) BigDecimal::of($this->payable())->minus(BigDecimal::of($this->amount_paid))->toScale(2);
    }

    /**
     * Determine if the expense is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->status === ExpenseStatus::Approved && ! BigDecimal::of($this->balanceDue())->isPositive();
    }

    /**
     * Determine if the approved expense is unpaid after its due date.
     */
    public function isOverdue(): bool
    {
        return $this->status === ExpenseStatus::Approved
            && BigDecimal::of($this->balanceDue())->isPositive()
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast();
    }

    /**
     * Get the label and badge colour describing the expense's current state.
     *
     * @return array{label: string, color: string}
     */
    public function displayStatus(): array
    {
        if ($this->status !== ExpenseStatus::Approved) {
            return ['label' => $this->status->label(), 'color' => $this->status->color()];
        }

        return match (true) {
            $this->isPaid() => ['label' => 'Paid', 'color' => 'green'],
            $this->isOverdue() => ['label' => 'Overdue', 'color' => 'red'],
            BigDecimal::of($this->amount_paid)->isPositive() => ['label' => 'Partially paid', 'color' => 'amber'],
            default => ['label' => 'To pay', 'color' => 'blue'],
        };
    }

    /**
     * Recalculate the amount paid from the payments.
     */
    public function refreshBalance(): void
    {
        $this->amount_paid = (string) $this->payments()->get()->reduce(
            fn (BigDecimal $sum, ExpensePayment $payment) => $sum->plus(BigDecimal::of($payment->amount)),
            BigDecimal::zero(),
        )->toScale(2);

        $this->paid_at = BigDecimal::of($this->balanceDue())->isPositive() ? null : ($this->paid_at ?? now());
        $this->save();
    }

    /**
     * Get the compact representation used in lists of related documents.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'type' => 'expense',
            'number' => $this->number,
            'title' => $this->title,
            'date' => $this->date->toDateString(),
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'total' => $this->total,
            'currency' => $this->currency,
            'customer' => $this->supplier_name,
            'url' => route('expenses.show', ['current_team' => $this->teamSlug(), 'expense' => $this->id]),
        ];
    }

    /**
     * Scope a query to expenses matching a search term.
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
            ->where('number', 'like', $like)
            ->orWhere('title', 'like', $like)
            ->orWhere('bill_number', 'like', $like)
            ->orWhere('supplier_name', 'like', $like)
            ->orWhere('supplier_ntn', 'like', $like));
    }

    /**
     * Scope a query to approved expenses that are not fully paid.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', ExpenseStatus::Approved)
            ->whereRaw('total - income_tax_withheld - sales_tax_withheld - amount_paid > 0');
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [
            'created_at', 'updated_at', 'deleted_at', 'amount_paid', 'paid_at', 'status', 'approved_by', 'approved_at',
            'rejection_reason', 'tax_rate', 'total', 'number',
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
            'date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'net_amount' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_claimable' => 'boolean',
            'income_tax_withheld' => 'decimal:2',
            'sales_tax_withheld' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => ExpenseStatus::class,
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
