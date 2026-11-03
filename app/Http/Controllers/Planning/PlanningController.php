<?php

namespace App\Http\Controllers\Planning;

use App\Enums\EventStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentTeam;
use App\Support\Notifier;
use App\Support\TeamRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PlanningController extends Controller
{
    /**
     * Show who is busy with what, per day, against their working hours.
     */
    public function index(Request $request): Response
    {
        $team = app(CurrentTeam::class)->team();
        $settings = $team->teamSettings();
        $weeks = $request->integer('weeks') === 2 ? 2 : 1;
        $start = $this->weekStart($request->string('week')->toString());
        $end = $start->addDays(7 * $weeks - 1)->endOfDay();
        $projectId = $request->integer('project') ?: null;

        $users = $team->members()->orderBy('name')->get(['users.id', 'users.name']);
        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $days[] = $day;
        }

        $events = CalendarEvent::query()
            ->with('attendees:id')
            ->between($start, $end)
            ->where('status', '!=', EventStatus::Cancelled)
            ->get();

        $tasks = Task::query()
            ->with('project:id,title')
            ->whereNotNull('assignee_id')
            ->whereBetween('planned_date', [$start, $end])
            ->when($projectId, fn (Builder $query, int $project) => $query->where('project_id', $project))
            ->get();

        $capacity = $settings->dailyCapacityMinutes();
        $workingDays = $settings->workingDays();

        $board = $users->map(function (User $user) use ($days, $events, $tasks, $capacity, $workingDays) {
            $row = [];

            foreach ($days as $day) {
                $items = [];

                foreach ($events as $event) {
                    $takesPart = $event->organizer_id === $user->id || $event->attendees->contains('id', $user->id);

                    if (! $takesPart || $event->starts_at->toDateString() > $day->toDateString() || $event->ends_at->toDateString() < $day->toDateString()) {
                        continue;
                    }

                    // Only single-day timed events take up hours; others are shown as labels.
                    $counts = ! $event->all_day && $event->starts_at->isSameDay($event->ends_at);

                    $items[] = [
                        'kind' => 'event',
                        'id' => $event->id,
                        'title' => $event->title,
                        'time' => $counts ? $event->starts_at->format('H:i') : null,
                        'minutes' => $counts ? (int) $event->starts_at->diffInMinutes($event->ends_at) : 0,
                    ];
                }

                foreach ($tasks as $task) {
                    if ($task->assignee_id !== $user->id || ! $task->planned_date?->isSameDay($day)) {
                        continue;
                    }

                    $items[] = [
                        'kind' => 'task',
                        'id' => $task->id,
                        'title' => $task->title,
                        'project' => $task->project?->title,
                        'done' => $task->status === TaskStatus::Done,
                        'minutes' => $task->planningMinutes(),
                        'planned_minutes' => $task->planned_minutes,
                        'due_date' => $task->due_date?->toDateString(),
                    ];
                }

                $row[] = [
                    'date' => $day->toDateString(),
                    'capacity' => in_array($day->isoWeekday(), $workingDays, true) ? $capacity : 0,
                    'minutes' => array_sum(array_column($items, 'minutes')),
                    'items' => $items,
                ];
            }

            return [
                'user' => ['id' => $user->id, 'name' => $user->name],
                'days' => $row,
                'minutes' => array_sum(array_column($row, 'minutes')),
                'capacity' => array_sum(array_column($row, 'capacity')),
            ];
        })->values();

        $unplanned = Task::query()
            ->with(['project:id,title', 'assignee:id,name'])
            ->where('status', '!=', TaskStatus::Done)
            ->whereNull('planned_date')
            ->when($projectId, fn (Builder $query, int $project) => $query->where('project_id', $project))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->limit(100)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'project' => $task->project?->title,
                'assignee_id' => $task->assignee_id,
                'assignee' => $task->assignee?->name,
                'due_date' => $task->due_date?->toDateString(),
                'estimated_minutes' => $task->estimated_minutes,
                'priority' => $task->priority->value,
                'priority_label' => $task->priority->label(),
                'priority_color' => $task->priority->color(),
            ])
            ->values();

        return Inertia::render('planning/Index', [
            'board' => $board,
            'days' => array_map(fn (CarbonImmutable $day) => [
                'date' => $day->toDateString(),
                'working' => in_array($day->isoWeekday(), $workingDays, true),
            ], $days),
            'unplanned' => $unplanned,
            'filters' => ['project' => $projectId, 'weeks' => $weeks],
            'week' => [
                'start' => $start->toDateString(),
                'previous' => $start->subWeek()->toDateString(),
                'next' => $start->addWeek()->toDateString(),
            ],
            'capacity' => $capacity,
            'users' => $users->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values(),
            'projects' => Project::query()->open()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->title])
                ->values(),
        ]);
    }

    /**
     * Plan a task for a person on a day (or take it out of the planning).
     */
    public function plan(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['nullable', 'required_with:planned_date', 'integer', TeamRule::member()],
            'planned_date' => ['nullable', 'date'],
            'planned_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ], [
            'assignee_id.required_with' => 'Choose who does the task.',
        ]);

        $task->update([
            'assignee_id' => $validated['assignee_id'] ?? $task->assignee_id,
            'planned_date' => $validated['planned_date'] ?? null,
            'planned_minutes' => isset($validated['planned_date']) ? ($validated['planned_minutes'] ?? null) : null,
        ]);

        if ($task->wasChanged('assignee_id')) {
            Notifier::taskAssigned($task, $request->user());
        }

        return back();
    }

    /**
     * The Monday of the requested week (this week by default).
     */
    protected function weekStart(string $date): CarbonImmutable
    {
        try {
            $day = $date !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $date) : null;
        } catch (Throwable) {
            $day = null;
        }

        return ($day ?: CarbonImmutable::today())->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }
}
