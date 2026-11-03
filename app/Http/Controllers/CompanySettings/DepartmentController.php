<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company profile(s): the legal entities / branches shown on documents.
 */
class DepartmentController extends Controller
{
    /**
     * List the departments.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/departments/Index', [
            'departments' => Department::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => $this->present($department)),
        ]);
    }

    /**
     * Show the form for a new department.
     */
    public function create(): Response
    {
        return Inertia::render('company-settings/departments/Form', [
            'department' => null,
            'provinces' => Province::options(),
        ]);
    }

    /**
     * Store a new department.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $validated) {
            $department = Department::create($validated);
            $this->storeLogo($request->file('logo'), $department);
            $this->ensureSingleDefault($department);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company profile created.')]);

        return to_route('settings.departments.index');
    }

    /**
     * Show the form for editing a department.
     */
    public function edit(Department $department): Response
    {
        return Inertia::render('company-settings/departments/Form', [
            'department' => $this->present($department),
            'provinces' => Province::options(),
        ]);
    }

    /**
     * Update a department.
     */
    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $validated, $department) {
            $department->update($validated);

            if ($request->boolean('remove_logo') && $department->logo_path) {
                Storage::disk('local')->delete($department->logo_path);
                $department->update(['logo_path' => null]);
            }

            $this->storeLogo($request->file('logo'), $department);
            $this->ensureSingleDefault($department);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company profile saved.')]);

        return to_route('settings.departments.index');
    }

    /**
     * Delete a department.
     */
    public function destroy(Department $department): RedirectResponse
    {
        abort_if($department->is_default, 422, 'The default company profile cannot be deleted.');

        if ($department->logo_path) {
            Storage::disk('local')->delete($department->logo_path);
        }

        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company profile deleted.')]);

        return to_route('settings.departments.index');
    }

    /**
     * Stream the department's logo.
     */
    public function logo(Department $department): StreamedResponse
    {
        abort_unless($department->logo_path && Storage::disk('local')->exists($department->logo_path), 404);

        return Storage::disk('local')->response($department->logo_path);
    }

    /**
     * Validate the department input.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        if (is_string($iban = $request->input('iban'))) {
            $request->merge(['iban' => strtoupper(str_replace(' ', '', $iban)) ?: null]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'ntn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z-]+$/'],
            'strn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', Rule::enum(Province::class)],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_title' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:34'],
            'document_footer' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_logo' => ['boolean'],
        ]);

        unset($validated['logo'], $validated['remove_logo']);
        $validated['country'] = $validated['country'] ?? 'Pakistan';

        return $validated;
    }

    /**
     * Store an uploaded logo for the department.
     */
    protected function storeLogo(mixed $file, Department $department): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        $team = app(CurrentTeam::class)->team();
        $path = $file->storeAs("teams/{$team->id}/logos", Str::uuid().'.'.$file->extension(), 'local');

        if ($department->logo_path) {
            Storage::disk('local')->delete($department->logo_path);
        }

        $department->update(['logo_path' => $path]);
    }

    /**
     * Make sure exactly one department is marked as default.
     */
    protected function ensureSingleDefault(Department $department): void
    {
        if ($department->is_default) {
            Department::query()->whereKeyNot($department->id)->update(['is_default' => false]);

            return;
        }

        if (! Department::query()->where('is_default', true)->exists()) {
            $department->update(['is_default' => true]);
        }
    }

    /**
     * Present a department for the pages.
     *
     * @return array<string, mixed>
     */
    protected function present(Department $department): array
    {
        return [
            ...$department->only([
                'id', 'name', 'legal_name', 'ntn', 'strn', 'email', 'phone', 'website',
                'address_line_1', 'address_line_2', 'city', 'postal_code', 'country',
                'bank_name', 'bank_account_title', 'bank_account_number', 'iban',
                'document_footer', 'is_default',
            ]),
            'province' => $department->province?->value,
            'province_label' => $department->province?->label(),
            'address' => implode(', ', $department->addressLines()),
            'logo_url' => $department->logo_path ? route('settings.departments.logo', ['department' => $department->id]) : null,
        ];
    }
}
