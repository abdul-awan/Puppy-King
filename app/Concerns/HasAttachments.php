<?php

namespace App\Concerns;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

trait HasAttachments
{
    /**
     * Get the files attached to the record, newest first.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * Store an uploaded file (in the team's private folder) and attach it to the record.
     */
    public function attachFile(UploadedFile $file, ?User $user = null): Attachment
    {
        $path = $file->store('teams/'.$this->getAttribute('team_id').'/attachments/'.now()->format('Y/m'), 'local');

        $attachment = new Attachment([
            'name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => (string) $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'user_id' => $user?->id,
        ]);
        $attachment->attachable()->associate($this);
        $attachment->save();

        return $attachment;
    }
}
