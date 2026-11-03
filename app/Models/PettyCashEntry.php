<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\LogsActivity;
use App\Enums\CashDirection;
use Carbon\CarbonImmutable;
use Database\Factories\PettyCashEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cash put into the cash box (e.g. withdrawn from the bank, or the opening balance) or taken out
 * of it other than for an expense (e.g. returned to the bank).
 *
 * @property int $id
 * @property int $team_id
 * @property CarbonImmutable $date
 * @property CashDirection $direction
 * @property string $amount
 * @property string $description
 * @property string|null $person
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $creator
 */
#[Fillable(['date', 'direction', 'amount', 'description', 'person', 'notes', 'created_by'])]
class PettyCashEntry extends Model
{
    /** @use HasFactory<PettyCashEntryFactory> */
    use BelongsToTeam, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'direction' => 'in',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
            'date' => 'date',
            'direction' => CashDirection::class,
            'amount' => 'decimal:2',
        ];
    }
}
