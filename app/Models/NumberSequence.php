<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A counter for one document type, department and numbering period.
 *
 * @property int $id
 * @property int $team_id
 * @property string $type
 * @property int $department_id
 * @property string $period
 * @property int $next_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'department_id', 'period', 'next_number'])]
class NumberSequence extends Model
{
    use BelongsToTeam;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'next_number' => 'integer',
        ];
    }
}
