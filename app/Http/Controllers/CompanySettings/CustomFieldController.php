<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomField;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    /**
     * List the custom fields.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/CustomFields', [
            'customFields' => CustomFieldResource::collection(
                CustomField::query()->orderBy('entity')->orderBy('sort')->orderBy('id')->get(),
            ),
            'entities' => CustomFieldEntity::options(),
            'types' => CustomFieldType::options(),
        ]);
    }

    /**
     * Store a new custom field.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $key = $this->uniqueKey($validated['entity'], $validated['label']);

        CustomField::create([...$validated, 'key' => $key]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field added.')]);

        return back();
    }

    /**
     * Update a custom field. The key and record type never change, so stored values keep working.
     */
    public function update(Request $request, CustomField $customField): RedirectResponse
    {
        $validated = $this->validated($request);
        unset($validated['entity']);

        $customField->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field saved.')]);

        return back();
    }

    /**
     * Delete a custom field (stored values are no longer shown).
     */
    public function destroy(CustomField $customField): RedirectResponse
    {
        $customField->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field deleted.')]);

        return back();
    }

    /**
     * Validate the custom field input.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        if (is_string($options = $request->input('options'))) {
            $request->merge(['options' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $options) ?: [])))]);
        }

        $validated = $request->validate([
            'entity' => ['required', Rule::enum(CustomFieldEntity::class)],
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['nullable', 'array', 'max:50'],
            'options.*' => ['string', 'max:100', 'distinct'],
            'is_required' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validated['type'] === CustomFieldType::Select->value && empty($validated['options'])) {
            throw ValidationException::withMessages(['options' => 'Add at least one option for a dropdown field.']);
        }

        if ($validated['type'] !== CustomFieldType::Select->value) {
            $validated['options'] = null;
        }

        $validated['sort'] ??= 0;

        return $validated;
    }

    /**
     * Build a key from the label that is unique for the record type.
     */
    protected function uniqueKey(string $entity, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base;
        $suffix = 2;

        while (CustomField::query()->where('team_id', app(CurrentTeam::class)->id())->where('entity', $entity)->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return Str::limit($key, 60, '');
    }
}
