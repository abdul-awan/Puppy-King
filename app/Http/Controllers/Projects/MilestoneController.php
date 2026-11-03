<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    /**
     * Add a milestone at the end of the project.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $milestone = new Milestone([...$this->validated($request), 'sort' => (int) $project->milestones()->max('sort') + 1]);
        $milestone->team_id = $project->team_id;
        $milestone->project_id = $project->id;
        $milestone->save();

        return back();
    }

    /**
     * Rename the milestone or change its due date.
     */
    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        $milestone->update($this->validated($request));

        return back();
    }

    /**
     * Delete the milestone; its tasks stay on the project.
     */
    public function destroy(Milestone $milestone): RedirectResponse
    {
        $milestone->delete();

        return back();
    }

    /**
     * Validate the milestone fields (name and optional due date).
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);
    }
}
