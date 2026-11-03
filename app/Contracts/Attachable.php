<?php

namespace App\Contracts;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

/**
 * A record that files can be attached to (implemented with the HasAttachments trait).
 */
interface Attachable
{
    /**
     * @return MorphMany<Attachment, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function attachments(): MorphMany;

    /**
     * Store an uploaded file and attach it to the record.
     */
    public function attachFile(UploadedFile $file, ?User $user = null): Attachment;
}
