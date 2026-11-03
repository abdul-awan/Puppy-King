<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\TemplateLayout;
use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\Team;
use App\Support\CurrentTeam;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\Documents\Templates\DocumentTemplates;
use App\Support\Documents\Templates\ElementTypes;
use App\Support\Documents\Templates\PageStyle;
use App\Support\Documents\Templates\Palette;
use App\Support\Documents\Templates\TemplateContext;
use App\Support\Documents\Templates\TemplateDefinition;
use App\Support\TeamSettings;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The template builder: a company's own templates for quotations, invoices and
 * credit notes, made of rows of elements that are dragged into place, each with
 * its own settings and style.
 */
class DocumentTemplateController extends Controller
{
    public function __construct(private DocumentTemplates $templates)
    {
        //
    }

    /**
     * Open the builder for a new template, starting from a ready-made template, an own one (?from=custom:12) or a blank page.
     */
    public function create(Request $request, DocumentPdf $pdf): Response
    {
        $team = app(CurrentTeam::class)->team();
        $from = (string) $request->query('from', TemplateLayout::Classic->value);
        $source = $this->templates->find($team, $from);
        $layout = $source->layout ?? TemplateLayout::tryFrom($from) ?? TemplateLayout::Classic;
        $definition = $from === 'blank' ? TemplateDefinition::blank() : ($this->templates->definition($team, $from) ?? $layout->definition());

        return $this->builder($team, $pdf, null, [
            'name' => $this->freeName($team, $source !== null ? $source->name.' (copy)' : 'My '.($from === 'blank' ? 'own' : $layout->label()).' template'),
            'layout' => $layout->value,
            'use_for' => [],
            'definition' => $definition->toArray(),
        ]);
    }

    /**
     * Store a new template.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();
        $validated = $this->validated($request, $team);

        $template = DB::transaction(function () use ($request, $team, $validated) {
            $template = DocumentTemplate::create([
                'name' => $validated['name'],
                'layout' => $validated['layout'],
                'design' => $this->definition($request)->toArray(),
                'created_by' => $request->user()?->id,
            ]);

            $this->useFor($team, $template, $validated['use_for'] ?? []);

            return $template;
        });

        $this->pruneImages($team);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The template “:name” is saved.', ['name' => $template->name])]);

        return to_route('settings.invoice-settings.edit');
    }

    /**
     * Open the builder for a template.
     */
    public function edit(DocumentTemplate $documentTemplate, DocumentPdf $pdf): Response
    {
        $team = app(CurrentTeam::class)->team();

        return $this->builder($team, $pdf, $documentTemplate, [
            'name' => $documentTemplate->name,
            'layout' => $documentTemplate->layout->value,
            'use_for' => array_keys(array_filter($this->templates->chosenKeys($team), fn (string $key) => $key === $documentTemplate->key())),
            'definition' => $documentTemplate->toDefinition()->toArray(),
        ]);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();
        $validated = $this->validated($request, $team, $documentTemplate);

        DB::transaction(function () use ($request, $team, $documentTemplate, $validated) {
            $documentTemplate->update([
                'name' => $validated['name'],
                'layout' => $validated['layout'],
                'design' => $this->definition($request)->toArray(),
            ]);

            $this->useFor($team, $documentTemplate, $validated['use_for'] ?? []);
        });

        $this->pruneImages($team);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The template “:name” is saved.', ['name' => $documentTemplate->name])]);

        return to_route('settings.invoice-settings.edit');
    }

    /**
     * Delete a template; documents that used it get the ready-made template it started from.
     */
    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();
        $replaced = array_keys(array_filter($this->templates->chosenKeys($team), fn (string $key) => $key === $documentTemplate->key()));

        DB::transaction(function () use ($team, $documentTemplate) {
            $this->useFor($team, $documentTemplate, []);
            $documentTemplate->delete();
        });

        $this->pruneImages($team);
        $message = __('The template “:name” is deleted.', ['name' => $documentTemplate->name]);

        if ($replaced !== []) {
            $message .= ' '.__(':documents now use the :layout template.', [
                'documents' => ucfirst(strtolower(implode(', ', array_map(fn (string $type) => DocumentTemplates::TYPES[$type], $replaced)))),
                'layout' => $documentTemplate->layout->label(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('settings.invoice-settings.edit');
    }

    /**
     * Show an example document as PDF with a template that is not saved yet (from the builder).
     */
    public function preview(Request $request, DocumentPdf $pdf): HttpResponse
    {
        $request->validate([
            'definition' => ['required', 'array'],
            'type' => ['sometimes', 'string'],
        ]);

        $team = app(CurrentTeam::class)->team();
        $type = array_key_exists((string) $request->input('type'), DocumentTemplates::TYPES) ? (string) $request->input('type') : 'invoice';

        return response($pdf->preview($team, $type, $this->definition($request), DocumentOptions::forTeam($team)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Render the builder.
     *
     * @param  array<string, mixed>  $values
     */
    private function builder(Team $team, DocumentPdf $pdf, ?DocumentTemplate $template, array $values): Response
    {
        $options = DocumentOptions::forTeam($team);
        $samples = [];

        foreach (array_keys(DocumentTemplates::TYPES) as $type) {
            $samples[$type] = $pdf->sample($team, $type, $options);
        }

        return Inertia::render('template-builder/Builder', [
            'template' => $template?->only(['id', 'name']),
            'values' => $values,
            'registry' => ElementTypes::forBrowser(),
            'structure' => [
                'layouts' => TemplateDefinition::LAYOUTS,
                'row_style' => TemplateDefinition::ROW_STYLE,
                'cell_style' => TemplateDefinition::CELL_STYLE,
                'max_rows' => TemplateDefinition::MAX_ROWS,
                'max_elements' => TemplateDefinition::MAX_ELEMENTS,
            ],
            'pageChoices' => [
                'fonts' => PageStyle::FONTS,
                'font_sizes' => PageStyle::FONT_SIZES,
                'margins' => PageStyle::MARGINS,
                'decorations' => PageStyle::DECORATIONS,
                'footer_aligns' => PageStyle::FOOTER_ALIGNS,
                'footer_borders' => PageStyle::FOOTER_BORDERS,
                'label_colors' => Palette::TEXTS,
            ],
            'presets' => [
                ...array_map(fn (TemplateLayout $layout) => [
                    'key' => $layout->value,
                    'name' => $layout->label(),
                    'description' => $layout->description(),
                    'definition' => $layout->definition()->toArray(),
                ], TemplateLayout::cases()),
                ['key' => 'blank', 'name' => 'Blank page', 'description' => 'A simple start with only the main elements.', 'definition' => TemplateDefinition::blank()->toArray()],
            ],
            'samples' => $samples,
            'gates' => $this->gates($options),
            'placeholders' => TemplateContext::PLACEHOLDERS,
            'images' => $this->images($team),
            'types' => InvoiceSettingsController::options(DocumentTemplates::TYPES),
        ]);
    }

    /**
     * Which elements the invoice settings switch off (the builder greys them out).
     *
     * @return array<string, bool>
     */
    private function gates(DocumentOptions $options): array
    {
        return [
            'qr' => $options->qrCode,
            'words' => $options->amountInWords,
            'payment' => $options->paymentDetails,
            'terms' => $options->terms,
            'stamp' => $options->statusStamp,
            'signature' => $options->signature !== 'none',
            'closing' => $options->closingNote !== null,
            'footer_text' => $options->companyFooter,
            'footer' => $options->footerBar,
        ];
    }

    /**
     * The images the company uploaded for templates.
     *
     * @return list<array{name: string, url: string}>
     */
    private function images(Team $team): array
    {
        $images = [];

        foreach (Storage::disk('local')->files("teams/{$team->id}/template-images") as $path) {
            $name = basename($path);

            if (TemplateContext::imagePath($team->id, $name) !== null) {
                $images[] = ['name' => $name, 'url' => route('settings.template-images.show', ['current_team' => $team->slug, 'name' => $name])];
            }
        }

        return $images;
    }

    /**
     * Validate a submitted template.
     *
     * @return array{name: string, layout: string, definition: array<mixed>, use_for?: list<string>}
     */
    private function validated(Request $request, Team $team, ?DocumentTemplate $template = null): array
    {
        /** @var array{name: string, layout: string, definition: array<mixed>, use_for?: list<string>} */
        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('document_templates', 'name')->where('team_id', $team->id)->ignore($template?->id),
                Rule::notIn(array_map(fn (TemplateLayout $layout) => $layout->label(), TemplateLayout::cases())),
            ],
            'layout' => ['required', Rule::enum(TemplateLayout::class)],
            'definition' => ['required', 'array'],
            'definition.rows' => [
                'required', 'array', 'min:1', 'max:'.TemplateDefinition::MAX_ROWS,
                function (string $attribute, mixed $rows, Closure $fail) {
                    $count = 0;

                    foreach ((array) $rows as $row) {
                        foreach ((array) (is_array($row) ? ($row['cells'] ?? []) : []) as $cell) {
                            $count += count((array) (is_array($cell) ? ($cell['elements'] ?? []) : []));
                        }
                    }

                    if ($count > TemplateDefinition::MAX_ELEMENTS) {
                        $fail('A template can have at most '.TemplateDefinition::MAX_ELEMENTS.' elements.');
                    }
                },
            ],
            'use_for' => ['sometimes', 'array'],
            'use_for.*' => ['string', Rule::in(array_keys(DocumentTemplates::TYPES)), 'distinct'],
        ], [
            'name.unique' => 'You already have a template with this name.',
            'name.not_in' => 'This is the name of a ready-made template; choose another name.',
            'definition.rows.required' => 'Add at least one row to the template.',
            'definition.rows.min' => 'Add at least one row to the template.',
            'definition.rows.max' => 'A template can have at most '.TemplateDefinition::MAX_ROWS.' rows.',
        ]);
    }

    /**
     * Get the submitted template, normalised. The whole submitted definition is used (validated data
     * would only hold the keys that have rules, and lose the page); normalising keeps only what is allowed.
     */
    private function definition(Request $request): TemplateDefinition
    {
        return TemplateDefinition::fromArray((array) $request->input('definition', []));
    }

    /**
     * Use the template for exactly the given kinds of documents; kinds that stop using it get the ready-made template it started from.
     *
     * @param  array<int, string>  $types
     */
    private function useFor(Team $team, DocumentTemplate $template, array $types): void
    {
        $chosen = $this->templates->chosenKeys($team);

        foreach (array_keys(DocumentTemplates::TYPES) as $type) {
            if (in_array($type, $types, true)) {
                $chosen[$type] = $template->key();
            } elseif ($chosen[$type] === $template->key()) {
                $chosen[$type] = $template->layout->value;
            }
        }

        $team->refresh()->update(['settings' => TeamSettings::merge($team->settings ?? [], ['documents' => ['templates' => $chosen]])]);
    }

    /**
     * Delete uploaded images that no template uses any more (after a day, so images of a template
     * that is still being made are kept).
     */
    private function pruneImages(Team $team): void
    {
        $used = [];

        foreach ($this->templates->own($team) as $template) {
            array_push($used, ...$template->toDefinition()->imageNames());
        }

        $disk = Storage::disk('local');

        foreach ($disk->files("teams/{$team->id}/template-images") as $path) {
            if (! in_array(basename($path), $used, true) && $disk->lastModified($path) < now()->subDay()->getTimestamp()) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Get a template name that is not taken yet ("Name", "Name 2", ...).
     */
    private function freeName(Team $team, string $name): string
    {
        $name = mb_substr($name, 0, 74);
        $taken = $this->templates->own($team)->pluck('name')->all();
        $candidate = $name;

        for ($number = 2; in_array($candidate, $taken, true); $number++) {
            $candidate = $name.' '.$number;
        }

        return $candidate;
    }
}
