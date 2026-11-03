<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment made to a supplier for an expense.
 *
 * @property int $id
 * @property int $team_id
 * @property int $expense_id
 * @property CarbonImmutable $date
 * @property string $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Expense $expense
 * @property-read User|null $user
 */
#[Fillable(['date', 'amount', 'method', 'reference', 'notes'])]
class ExpensePayment extends Model
{
    use BelongsToTeam;

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'method' => PaymentMethod::class,
        ];
    }
}
