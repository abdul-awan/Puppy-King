<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * List the tags with how often each one is used.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/Tags', [
            'tags' => Tag::query()
                ->withCount(['companies', 'contacts'])
                ->orderBy('name')
                ->get()
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'usage' => (int) $tag->getAttribute('companies_count') + (int) $tag->getAttribute('contacts_count'),
                ]),
            'colors' => array_map(fn (string $color) => ['value' => $color, 'label' => ucfirst($color)], Tag::COLORS),
        ]);
    }

    /**
     * Store a new tag.
     */
    public function store(Request $request): RedirectResponse
    {
        Tag::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag added.')]);

        return back();
    }

    /**
     * Update a tag.
     */
    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $tag->update($this->validated($request, $tag));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag saved.')]);

        return back();
    }

    /**
     * Delete a tag (it is removed from all records).
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return back();
    }

    /**
     * Validate the tag input.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Tag $tag = null): array
    {
        $unique = Rule::unique('tags', 'name')->where('team_id', app(CurrentTeam::class)->id());

        return $request->validate([
            'name' => ['required', 'string', 'max:50', $tag ? $unique->ignore($tag->id) : $unique],
            'color' => ['required', Rule::in(Tag::COLORS)],
        ]);
    }
}
