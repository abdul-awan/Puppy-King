<?php

namespace App\Http\Controllers\Calendar;

use App\Enums\AccessLevel;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\Module;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\CalendarEventRequest;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Project;
use App\Models\Task;
use App\Support\CurrentTeam;
use App\Support\TeamOptions;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class CalendarController extends Controller
{
    /**
     * Show the calendar: a week, a month or an agenda of what is coming.
     */
    public function index(Request $request): Response
    {
        $view = in_array($request->string('view')->toString(), ['week', 'month', 'agenda'], true) ? $request->string('view')->toString() : 'week';
        $user = $request->string('user')->toString();
        $filters = [
            'view' => $view,
            'user' => $user === 'all' || ctype_digit($user) ? $user : 'me',
        ];

        $anchor = $this->date($request->string('date')->toString());
        [$start, $end, $previous, $next] = $this->range($view, $anchor);
        $userId = $filters['user'] === 'me' ? $request->user()->id : (ctype_digit($filters['user']) ? (int) $filters['user'] : null);

        $events = CalendarEvent::query()
            ->with(['organizer:id,name', 'attendees:id,name', 'company:id,name', 'contact:id,first_name,last_name', 'deal:id,title', 'project:id,title'])
            ->between($start, $end)
            ->when($userId, fn (Builder $query, int $id) => $query->forUser($id))
            ->orderBy('starts_at')
            ->get();

        $team = app(CurrentTeam::class)->team();
        $tasks = $request->user()->canAccessModule($team, Module::Projects, AccessLevel::Read)
            ? Task::query()
                ->with('project:id,title')
                ->where('status', '!=', TaskStatus::Done)
                ->whereBetween('due_date', [$start, $end])
                ->when($userId, fn (Builder $query, int $id) => $query->where('assignee_id', $id))
                ->orderBy('due_date')
                ->get()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'due_date' => $task->due_date?->toDateString(),
                    'project' => $task->project?->title,
                    'priority' => $task->priority->value,
                ])->values()
            : [];

        return Inertia::render('calendar/Index', [
            'events' => CalendarEventResource::collection($events),
            'tasks' => $tasks,
            'filters' => $filters,
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'anchor' => $anchor->toDateString(),
                'previous' => $previous->toDateString(),
                'next' => $next->toDateString(),
            ],
            'users' => TeamOptions::users(),
            'types' => EventType::options(),
            'planFor' => $request->boolean('plan') ? $this->planFor($request) : null,
        ]);
    }

    /**
     * Plan an event.
     */
    public function store(CalendarEventRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $event = new CalendarEvent($request->safe()->except('attendee_ids'));
            $event->team_id = app(CurrentTeam::class)->team()->id;
            $event->organizer_id = $request->user()->id;
            $event->created_by = $request->user()->id;
            $this->normalizeTimes($event);
            $event->save();

            $event->attendees()->sync($this->attendeeIds($request, $event));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to the calendar.')]);

        return back();
    }

    /**
     * Change an event (also used to report how it went).
     */
    public function update(CalendarEventRequest $request, CalendarEvent $event): RedirectResponse
    {
        DB::transaction(function () use ($request, $event) {
            $event->fill($request->safe()->except('attendee_ids'));
            $this->normalizeTimes($event);

            if ($event->isDirty(['starts_at', 'reminder_minutes'])) {
                $event->reminded_at = null;
            }

            $event->save();

            $event->attendees()->sync($this->attendeeIds($request, $event));
        });

        return back();
    }

    /**
     * Mark the event as done, with what came out of it.
     */
    public function complete(Request $request, CalendarEvent $event): RedirectResponse
    {
        $validated = $request->validate(['outcome' => ['nullable', 'string', 'max:10000']]);

        $event->update(['status' => EventStatus::Done, 'outcome' => $validated['outcome'] ?? $event->outcome]);

        return back();
    }

    /**
     * Delete an event.
     */
    public function destroy(CalendarEvent $event): RedirectResponse
    {
        $event->delete();

        return back();
    }

    /**
     * Download the event as an .ics file (to add it to Google, Outlook or a phone).
     */
    public function ics(CalendarEvent $event): HttpResponse
    {
        return response($event->toIcs(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($event->title ?: 'event').'.ics"',
        ]);
    }

    /**
     * The record(s) a new event is planned for, when the calendar is opened from their page.
     *
     * @return array<string, array<string, mixed>|null>
     */
    protected function planFor(Request $request): array
    {
        $deal = $request->integer('deal') ? Deal::query()->find($request->integer('deal'), ['id', 'title', 'company_id']) : null;
        $project = $request->integer('project') ? Project::query()->find($request->integer('project'), ['id', 'title', 'company_id']) : null;
        $contact = $request->integer('contact') ? Contact::query()->find($request->integer('contact')) : null;

        // A deal or project brings its customer along.
        $companyId = $request->integer('company') ?: ($deal->company_id ?? $project?->company_id);
        $company = $companyId ? Company::query()->find($companyId, ['id', 'name']) : null;

        return [
            'company' => $company?->only(['id', 'name']),
            'contact' => $contact ? ['id' => $contact->id, 'name' => $contact->full_name] : null,
            'deal' => $deal?->only(['id', 'title']),
            'project' => $project?->only(['id', 'title']),
        ];
    }

    /**
     * All-day events run from the start of the first day to the end of the last.
     */
    protected function normalizeTimes(CalendarEvent $event): void
    {
        if ($event->all_day) {
            $event->starts_at = $event->starts_at->startOfDay();
            $event->ends_at = $event->ends_at->endOfDay();
        }
    }

    /**
     * The attendees to keep: the chosen team members and the organizer.
     *
     * @return array<int, int>
     */
    protected function attendeeIds(CalendarEventRequest $request, CalendarEvent $event): array
    {
        $ids = array_map('intval', (array) $request->validated('attendee_ids', []));

        if ($event->organizer_id !== null) {
            $ids[] = $event->organizer_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * The first and last day shown, and the anchors of the previous and next period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    protected function range(string $view, CarbonImmutable $anchor): array
    {
        return match ($view) {
            'month' => [
                $anchor->startOfMonth()->startOfWeek(CarbonInterface::MONDAY),
                $anchor->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY),
                $anchor->subMonthNoOverflow()->startOfMonth(),
                $anchor->addMonthNoOverflow()->startOfMonth(),
            ],
            'agenda' => [
                $anchor->startOfDay(),
                $anchor->addDays(30)->endOfDay(),
                $anchor->subDays(30),
                $anchor->addDays(30),
            ],
            default => [
                $anchor->startOfWeek(CarbonInterface::MONDAY),
                $anchor->endOfWeek(CarbonInterface::SUNDAY),
                $anchor->subWeek(),
                $anchor->addWeek(),
            ],
        };
    }

    /**
     * Parse the requested date (today when missing or invalid).
     */
    protected function date(string $value): CarbonImmutable
    {
        try {
            $date = $value !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $value) : null;
        } catch (Throwable) {
            $date = null;
        }

        return ($date ?: CarbonImmutable::today())->startOfDay();
    }
}
