<?php

namespace App\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    /**
     * Get the tags assigned to the record.
     *
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('name');
    }

    /**
     * Replace the record's tags with the given tag ids (limited to the record's team).
     *
     * @param  array<int, int|string>|null  $tagIds
     */
    public function syncTags(?array $tagIds): void
    {
        $ids = Tag::query()
            ->where('team_id', $this->getAttribute('team_id'))
            ->whereKey($tagIds ?? [])
            ->pluck('id')
            ->all();

        $this->tags()->sync($ids);
    }

    /**
     * Scope a query to records that have the given tag.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithTag(Builder $query, int $tagId): void
    {
        $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tagId));
    }
}
