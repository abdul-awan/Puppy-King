<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\TeamRole;
use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Models\User;
use App\Support\CurrentTeam;
use App\Support\RecordTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * Add a note to a record.
     */
    public function store(NoteRequest $request): RedirectResponse
    {
        $type = (string) $request->validated('notable_type');

        $this->authorizeModule($request->user(), $type);

        $record = RecordTypes::find($type, (int) $request->validated('notable_id'));

        $note = new Note([
            'body' => $request->validated('body'),
            'is_pinned' => $request->boolean('is_pinned'),
            'user_id' => $request->user()->id,
        ]);
        $note->notable()->associate($record);
        $note->save();

        return back();
    }

    /**
     * Update a note.
     */
    public function update(NoteRequest $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request->user(), $note);

        $note->update([
            'body' => $request->validated('body'),
            'is_pinned' => $request->boolean('is_pinned'),
        ]);

        return back();
    }

    /**
     * Delete a note.
     */
    public function destroy(Request $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request->user(), $note);

        $note->delete();

        return back();
    }

    /**
     * Ensure the user may write to the module of the record type.
     */
    protected function authorizeModule(User $user, string $type): void
    {
        abort_unless($user->canAccessModule(app(CurrentTeam::class)->team(), RecordTypes::module($type), AccessLevel::Write), 403);
    }

    /**
     * Ensure the user may change the note: its author or a team admin.
     */
    protected function authorizeNote(User $user, Note $note): void
    {
        $this->authorizeModule($user, $note->notable_type);

        $isAdmin = $user->teamRole(app(CurrentTeam::class)->team())?->isAtLeast(TeamRole::Admin) ?? false;

        abort_unless($note->user_id === $user->id || $isAdmin, 403);
    }
}
