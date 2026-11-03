<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message in a ticket: a reply to the customer, a message from the customer or an internal note.
 *
 * @property int $id
 * @property int $team_id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string|null $author_name
 * @property string $body
 * @property bool $is_internal
 * @property CarbonImmutable|null $emailed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'author_name', 'body', 'is_internal'])]
class TicketMessage extends Model
{
    use BelongsToTeam;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine if the customer wrote the message (a team member wrote it otherwise).
     */
    public function fromCustomer(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'emailed_at' => 'datetime',
        ];
    }
}
