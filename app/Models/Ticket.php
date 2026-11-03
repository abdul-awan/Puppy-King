<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Contracts\Summarizable;
use App\Enums\TaskPriority;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A question or problem of a customer, handled as a conversation.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $number
 * @property string $subject
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property int|null $project_id
 * @property int|null $assignee_id
 * @property TicketStatus $status
 * @property TaskPriority $priority
 * @property TicketChannel $channel
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property string $public_token
 * @property CarbonImmutable|null $first_response_at
 * @property CarbonImmutable|null $last_customer_message_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Company|null $company
 * @property-read Contact|null $contact
 * @property-read Project|null $project
 * @property-read User|null $assignee
 * @property-read Collection<int, TicketMessage> $messages
 */
#[Fillable([
    'subject', 'company_id', 'contact_id', 'project_id', 'assignee_id', 'status', 'priority', 'channel',
    'customer_name', 'customer_email', 'created_by',
])]
class Ticket extends Model implements Attachable, Summarizable
{
    /** @use HasFactory<TicketFactory> */
    use BelongsToTeam, HasAttachments, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'priority' => 'normal',
        'channel' => 'email',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->public_token)) {
                $ticket->public_token = Str::random(48);
            }
        });

        static::saving(function (Ticket $ticket) {
            if ($ticket->isDirty('status')) {
                $ticket->closed_at = $ticket->status === TicketStatus::Closed ? now() : null;
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The conversation, oldest first.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Get the link the customer uses to follow the ticket and reply.
     */
    public function publicUrl(): string
    {
        return route('public.tickets.show', ['token' => $this->public_token]);
    }

    /**
     * Get the customer's name as shown on the ticket.
     */
    public function customerLabel(): string
    {
        return $this->contact->full_name ?? $this->company->name ?? $this->customer_name ?? $this->customer_email ?? '—';
    }

    /**
     * Get the address replies are e-mailed to.
     */
    public function customerEmail(): ?string
    {
        return $this->customer_email ?? $this->contact->email ?? $this->company?->email;
    }

    /**
     * Get the compact representation used in lists of related records.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => 'ticket',
            'number' => $this->number,
            'title' => $this->subject,
            'date' => ($this->created_at ?? CarbonImmutable::now())->toDateString(),
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'total' => '0',
            'currency' => '',
            'customer' => $this->customerLabel(),
            'url' => route('tickets.show', ['current_team' => $this->teamSlug(), 'ticket' => $this->id]),
        ];
    }

    /**
     * Scope a query to tickets that are not closed.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', TicketStatus::Closed);
    }

    /**
     * Scope a query to tickets matching a search term.
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
            ->orWhere('subject', 'like', $like)
            ->orWhere('customer_name', 'like', $like)
            ->orWhere('customer_email', 'like', $like)
            ->orWhereHas('company', fn (Builder $query) => $query->where('name', 'like', $like)));
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'public_token', 'first_response_at', 'last_customer_message_at', 'closed_at', 'number'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TaskPriority::class,
            'channel' => TicketChannel::class,
            'first_response_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
