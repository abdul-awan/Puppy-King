<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\Notifier;
use App\Support\TeamOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * List tasks: by default the open tasks of the current user.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $assignee = $request->string('assignee')->toString();
        $due = $request->string('due')->toString();

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($status, ['all', ...array_column(TaskStatus::cases(), 'value')], true) ? $status : 'open',
            'assignee' => $assignee === 'all' || ctype_digit($assignee) ? $assignee : 'me',
            'project' => $request->integer('project') ?: null,
            'due' => in_array($due, ['overdue', 'today', 'week'], true) ? $due : null,
        ];

        $tasks = Task::query()
            ->with(['project:id,number,title', 'company:id,name', 'deal:id,title', 'assignee:id,name'])
            ->search($filters['search'])
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->open())
            ->when(! in_array($filters['status'], ['open', 'all'], true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $request->user()->id))
            ->when(ctype_digit($filters['assignee']), fn (Builder $query) => $query->where('assignee_id', (int) $filters['assignee']))
            ->when($filters['project'], fn (Builder $query, int $project) => $query->where('project_id', $project))
            ->when($filters['due'] === 'overdue', fn (Builder $query) => $query->open()->whereDate('due_date', '<', today()))
            ->when($filters['due'] === 'today', fn (Builder $query) => $query->whereDate('due_date', today()))
            ->when($filters['due'] === 'week', fn (Builder $query) => $query->whereBetween('due_date', [today()->startOfDay(), today()->addDays(7)->endOfDay()]))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('tasks/Index', [
            'tasks' => TaskResource::collection($tasks),
            'filters' => $filters,
            'users' => TeamOptions::users(),
            'projects' => Project::query()->open()->orderBy('title')->get(['id', 'number', 'title'])
                ->map(fn (Project $project) => ['id' => $project->id, 'name' => trim(($project->number ?? '').' '.$project->title)])
                ->values(),
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
            'workTypes' => TeamOptions::workTypes(),
        ]);
    }

    /**
     * Create a task.
     */
    public function store(TaskRequest $request): RedirectResponse
    {
        $task = new Task([...$request->validated(), 'created_by' => $request->user()->id]);
        $this->fillFromProject($task);
        $task->sort = (int) Task::query()->where('project_id', $task->project_id)->max('sort') + 1;
        $task->save();

        Notifier::taskAssigned($task, $request->user());

        return back();
    }

    /**
     * Update a task.
     */
    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->fill($request->validated());
        $this->fillFromProject($task);
        $task->save();

        if ($task->wasChanged('assignee_id')) {
            Notifier::taskAssigned($task, $request->user());
        }

        return back();
    }

    /**
     * Tick a task off (or open it again).
     */
    public function complete(Task $task): RedirectResponse
    {
        $task->status = $task->status === TaskStatus::Done ? TaskStatus::Todo : TaskStatus::Done;
        $task->save();

        return back();
    }

    /**
     * Delete a task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return back();
    }

    /**
     * A project task belongs to the project's customer.
     */
    protected function fillFromProject(Task $task): void
    {
        if ($task->project_id !== null && $task->isDirty('project_id')) {
            $task->company_id = Project::query()->whereKey($task->project_id)->value('company_id');
        }
    }
}
