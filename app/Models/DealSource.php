<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Where a deal came from (website, referral, exhibition...).
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'sort'])]
class DealSource extends Model
{
    use BelongsToTeam;
}
