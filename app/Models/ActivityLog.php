<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $causer_id
 * @property string $event
 * @property string|null $description
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property-read User|null $causer
 */
#[Fillable(['team_id', 'subject_type', 'subject_id', 'causer_id', 'event', 'description', 'properties'])]
class ActivityLog extends Model
{
    use BelongsToTeam;

    /**
     * The name of the "updated at" column (the log is append-only).
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'activity_log';

    /**
     * Get the record the entry is about.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who caused the entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }
}
