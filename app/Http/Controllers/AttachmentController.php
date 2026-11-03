<?php

namespace App\Http\Controllers;

use App\Contracts\Attachable;
use App\Enums\AccessLevel;
use App\Enums\TeamRole;
use App\Http\Requests\AttachmentRequest;
use App\Models\Attachment;
use App\Models\User;
use App\Support\CurrentTeam;
use App\Support\RecordTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Upload files and attach them to a record.
     */
    public function store(AttachmentRequest $request): RedirectResponse
    {
        $type = (string) $request->validated('attachable_type');

        $this->authorizeModule($request->user(), $type, AccessLevel::Write);

        $record = RecordTypes::find($type, (int) $request->validated('attachable_id'));

        abort_unless($record instanceof Attachable, 404);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);

        foreach ($files as $file) {
            $record->attachFile($file, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} File uploaded.|[2,*] :count files uploaded.', count($files))]);

        return back();
    }

    /**
     * Download an attached file.
     */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeModule($request->user(), $attachment->attachable_type, AccessLevel::Read);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->name);
    }

    /**
     * Delete an attached file.
     */
    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $user = $request->user();

        $this->authorizeModule($user, $attachment->attachable_type, AccessLevel::Write);

        $isAdmin = $user->teamRole(app(CurrentTeam::class)->team())?->isAtLeast(TeamRole::Admin) ?? false;

        abort_unless($attachment->user_id === $user->id || $isAdmin, 403);

        $attachment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File deleted.')]);

        return back();
    }

    /**
     * Ensure the user has the given access to the module of the record type.
     */
    protected function authorizeModule(User $user, string $type, AccessLevel $level): void
    {
        abort_unless($user->canAccessModule(app(CurrentTeam::class)->team(), RecordTypes::module($type), $level), 403);
    }
}
