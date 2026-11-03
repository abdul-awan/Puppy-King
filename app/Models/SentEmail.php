<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A copy of an e-mail the application sent on behalf of a user.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $emailable_type
 * @property int|null $emailable_id
 * @property int|null $user_id
 * @property string $to
 * @property string|null $cc
 * @property string $subject
 * @property string $body
 * @property array<int, string>|null $attachments
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['team_id', 'user_id', 'to', 'cc', 'subject', 'body', 'attachments', 'status', 'error', 'sent_at'])]
class SentEmail extends Model
{
    use BelongsToTeam;

    /**
     * Get the record the e-mail was about.
     *
     * @return MorphTo<Model, $this>
     */
    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who sent the e-mail.
     *
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
            'attachments' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
