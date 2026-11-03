<?php

namespace App\Support;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Note;
use App\Models\Project;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds the combined history (activity, notes and e-mails) of a record.
 */
final class Timeline
{
    /**
     * Fields for which the old and new value are shown in the history.
     *
     * @var array<int, string>
     */
    private const VALUE_FIELDS = ['status', 'relationship', 'priority', 'name', 'title', 'email', 'phone', 'value', 'due_date', 'expected_close_date'];

    /**
     * Get the timeline items for a record, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for(Model $record, int $limit = 100): array
    {
        $type = $record->getMorphClass();
        $id = $record->getKey();
        $userId = auth()->id();
        $items = [];

        $activities = ActivityLog::query()
            ->with('causer:id,name')
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->latest('id')
            ->limit($limit)
            ->get();

        foreach ($activities as $activity) {
            $items[] = [
                'key' => 'activity-'.$activity->id,
                'kind' => 'activity',
                'event' => $activity->event,
                'description' => $activity->description ?? self::describe($activity),
                'user' => $activity->causer?->only(['id', 'name']),
                'created_at' => $activity->created_at?->toIso8601String(),
            ];
        }

        $notes = Note::query()
            ->with('user:id,name')
            ->where('notable_type', $type)
            ->where('notable_id', $id)
            ->latest('id')
            ->limit($limit)
            ->get();

        foreach ($notes as $note) {
            $items[] = [
                'key' => 'note-'.$note->id,
                'kind' => 'note',
                'id' => $note->id,
                'body' => $note->body,
                'is_pinned' => $note->is_pinned,
                'can_edit' => $note->user_id === $userId,
                'user' => $note->user?->only(['id', 'name']),
                'created_at' => $note->created_at?->toIso8601String(),
            ];
        }

        $emails = SentEmail::query()
            ->with('user:id,name')
            ->where('emailable_type', $type)
            ->where('emailable_id', $id)
            ->latest('id')
            ->limit($limit)
            ->get();

        foreach ($emails as $email) {
            $items[] = [
                'key' => 'email-'.$email->id,
                'kind' => 'email',
                'id' => $email->id,
                'to' => $email->to,
                'subject' => $email->subject,
                'body' => $email->body,
                'status' => $email->status,
                'error' => $email->error,
                'user' => $email->user?->only(['id', 'name']),
                'created_at' => $email->created_at?->toIso8601String(),
            ];
        }

        foreach (self::events($record, $limit) as $event) {
            $items[] = [
                'key' => 'event-'.$event->id,
                'kind' => 'event',
                'id' => $event->id,
                'type' => $event->type->value,
                'type_label' => $event->type->label(),
                'title' => $event->title,
                'location' => $event->location,
                'starts_at' => $event->starts_at->toIso8601String(),
                'all_day' => $event->all_day,
                'status' => $event->status->value,
                'outcome' => $event->outcome,
                'user' => $event->organizer?->only(['id', 'name']),
                // Sorted by the moment of the event, so planned events show at the top.
                'created_at' => $event->starts_at->toIso8601String(),
            ];
        }

        usort($items, fn (array $a, array $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return array_slice($items, 0, $limit);
    }

    /**
     * Get the calendar events about a company, contact, deal or project.
     *
     * @return Collection<int, CalendarEvent>
     */
    private static function events(Model $record, int $limit): Collection
    {
        $column = match (true) {
            $record instanceof Company => 'company_id',
            $record instanceof Contact => 'contact_id',
            $record instanceof Deal => 'deal_id',
            $record instanceof Project => 'project_id',
            default => null,
        };

        $user = auth()->user();
        $team = app(CurrentTeam::class)->get();

        if ($column === null || ! $user instanceof User || $team === null || ! $user->canAccessModule($team, Module::Calendar, AccessLevel::Read)) {
            return new Collection;
        }

        return CalendarEvent::query()
            ->with('organizer:id,name')
            ->where($column, $record->getKey())
            ->latest('starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Describe an activity entry in words.
     */
    private static function describe(ActivityLog $activity): string
    {
        return match ($activity->event) {
            'created' => 'Created',
            'deleted' => 'Deleted',
            'updated' => self::describeUpdate($activity->properties ?? []),
            default => Str::headline($activity->event),
        };
    }

    /**
     * Describe the changes of an "updated" entry.
     *
     * @param  array<string, mixed>  $properties
     */
    private static function describeUpdate(array $properties): string
    {
        /** @var array<int, string> $fields */
        $fields = $properties['fields'] ?? [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        $new = is_array($properties['new'] ?? null) ? $properties['new'] : [];
        $parts = [];

        foreach ($fields as $field) {
            $label = Str::of($field)->replaceLast('_id', '')->replace('_', ' ')->ucfirst()->toString();

            if (in_array($field, self::VALUE_FIELDS, true)) {
                $parts[] = $label.': '.self::value($old[$field] ?? null).' → '.self::value($new[$field] ?? null);
            } else {
                $parts[] = Str::lower($label);
            }
        }

        return 'Updated '.implode(', ', $parts);
    }

    /**
     * Format a logged value for display.
     */
    private static function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        $value = (string) $value;

        return preg_match('/^[a-z_]+$/', $value) === 1 ? Str::of($value)->replace('_', ' ')->ucfirst()->toString() : $value;
    }
}
