<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\LogsActivity;
use App\Enums\EventStatus;
use App\Enums\EventType;
use Carbon\CarbonImmutable;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A meeting, call or appointment in the calendar, optionally about a customer, deal or project.
 *
 * @property int $id
 * @property int $team_id
 * @property EventType $type
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $all_day
 * @property int|null $organizer_id
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property int|null $deal_id
 * @property int|null $project_id
 * @property EventStatus $status
 * @property string|null $outcome
 * @property int|null $reminder_minutes
 * @property CarbonImmutable|null $reminded_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $organizer
 * @property-read Company|null $company
 * @property-read Contact|null $contact
 * @property-read Deal|null $deal
 * @property-read Project|null $project
 * @property-read Collection<int, User> $attendees
 */
#[Fillable([
    'type', 'title', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'company_id', 'contact_id',
    'deal_id', 'project_id', 'status', 'outcome', 'reminder_minutes',
])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use BelongsToTeam, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'meeting',
        'status' => 'planned',
        'all_day' => false,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * The team members taking part (the organizer included).
     *
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_event_attendees');
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
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * Scope a query to events that take place (partly) between two moments.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBetween(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $query->where('starts_at', '<=', $end)->where('ends_at', '>=', $start);
    }

    /**
     * Scope a query to events of a user (as organizer or attendee).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('organizer_id', $userId)
            ->orWhereHas('attendees', fn (Builder $query) => $query->whereKey($userId)));
    }

    /**
     * Get the event as an iCalendar (.ics) file, to add it to Google, Outlook or a phone.
     */
    public function toIcs(): string
    {
        $escape = fn (?string $text) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $text);
        $utc = fn (CarbonImmutable $moment) => $moment->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$escape((string) config('app.name')).'//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$this->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$utc(CarbonImmutable::now()),
            ...($this->all_day
                ? ['DTSTART;VALUE=DATE:'.$this->starts_at->format('Ymd'), 'DTEND;VALUE=DATE:'.$this->ends_at->addDay()->format('Ymd')]
                : ['DTSTART:'.$utc($this->starts_at), 'DTEND:'.$utc($this->ends_at)]),
            'SUMMARY:'.$escape($this->title),
        ];

        if (filled($this->location)) {
            $lines[] = 'LOCATION:'.$escape($this->location);
        }

        if (filled($this->description)) {
            $lines[] = 'DESCRIPTION:'.$escape($this->description);
        }

        if ($this->reminder_minutes !== null) {
            array_push($lines, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:'.$escape($this->title), 'TRIGGER:-PT'.$this->reminder_minutes.'M', 'END:VALARM');
        }

        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'reminded_at'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'reminder_minutes' => 'integer',
            'reminded_at' => 'datetime',
        ];
    }
}
