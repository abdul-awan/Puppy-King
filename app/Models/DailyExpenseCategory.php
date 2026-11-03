<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A category for daily expenses (tea, fuel, stationery...).
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'sort'])]
class DailyExpenseCategory extends Model
{
    use BelongsToTeam;
}
