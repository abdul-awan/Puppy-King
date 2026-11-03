<?php

namespace App\Concerns;

use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotes
{
    /**
     * Get the notes attached to the record, pinned first then newest first.
     *
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')
            ->orderByDesc('is_pinned')
            ->latest('id');
    }
}
