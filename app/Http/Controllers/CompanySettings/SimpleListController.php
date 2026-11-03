<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base controller for settings lists that only have a name and an order
 * (deal sources, lost reasons, expense categories...).
 */
abstract class SimpleListController extends Controller
{
    /**
     * The model class managed by the controller.
     *
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * The Inertia page that renders the list.
     */
    abstract protected function page(): string;

    /**
     * The name of the records, used in messages (e.g. "Deal source").
     */
    abstract protected function label(): string;

    /**
     * List the records.
     */
    public function index(): Response
    {
        $model = $this->model();

        return Inertia::render($this->page(), [
            'items' => $model::query()->orderBy('sort')->orderBy('name')->get(['id', 'name', 'sort']),
        ]);
    }

    /**
     * Store a new record.
     */
    public function store(Request $request): RedirectResponse
    {
        $model = $this->model();
        $model::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':label added.', ['label' => $this->label()])]);

        return back();
    }

    /**
     * Update a record.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $model = $this->model();
        $model::query()->findOrFail($id)->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':label saved.', ['label' => $this->label()])]);

        return back();
    }

    /**
     * Delete a record.
     */
    public function destroy(int $id): RedirectResponse
    {
        $model = $this->model();
        $model::query()->findOrFail($id)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':label deleted.', ['label' => $this->label()])]);

        return back();
    }

    /**
     * Validate the input.
     *
     * @return array{name: string, sort: int}
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        return ['name' => $validated['name'], 'sort' => (int) ($validated['sort'] ?? 0)];
    }
}
