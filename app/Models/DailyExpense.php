<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Factories\DailyExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Day-to-day spending such as tea, fuel, stationery or a courier, usually paid from the cash box
 * (petty cash). Unlike a supplier's bill (an Expense) it has no sales tax, approval or due date.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $number
 * @property CarbonImmutable $date
 * @property int|null $category_id
 * @property string $description
 * @property string $amount
 * @property PaymentMethod $payment_method
 * @property string|null $paid_by
 * @property string|null $paid_to
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read DailyExpenseCategory|null $category
 * @property-read User|null $creator
 */
#[Fillable(['date', 'category_id', 'description', 'amount', 'payment_method', 'paid_by', 'paid_to', 'reference', 'notes', 'created_by'])]
class DailyExpense extends Model implements Attachable
{
    /** @use HasFactory<DailyExpenseFactory> */
    use BelongsToTeam, HasAttachments, HasFactory, HasNotes, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_method' => 'cash',
    ];

    /**
     * @return BelongsTo<DailyExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DailyExpenseCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine if the expense was paid from the cash box.
     */
    public function isCash(): bool
    {
        return $this->payment_method === PaymentMethod::Cash;
    }

    /**
     * Scope a query to daily expenses matching a search term.
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
            ->orWhere('description', 'like', $like)
            ->orWhere('paid_to', 'like', $like)
            ->orWhere('paid_by', 'like', $like)
            ->orWhere('reference', 'like', $like));
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'number'];
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
            'payment_method' => PaymentMethod::class,
        ];
    }
}
