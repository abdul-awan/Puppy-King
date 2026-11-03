<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property int $days
 * @property bool $end_of_month
 * @property bool $is_default
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'days', 'end_of_month', 'is_default', 'sort'])]
class PaymentTerm extends Model
{
    use BelongsToTeam;

    /**
     * Calculate the due date for a document issued on the given date.
     */
    public function dueDate(DateTimeInterface $issueDate): CarbonImmutable
    {
        $date = CarbonImmutable::instance($issueDate)->startOfDay();

        if ($this->end_of_month) {
            $date = $date->endOfMonth()->startOfDay();
        }

        return $date->addDays($this->days);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'end_of_month' => 'boolean',
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
