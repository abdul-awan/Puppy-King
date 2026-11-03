<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\TemplateLayout;
use App\Http\Controllers\Controller;
use App\Support\CurrentTeam;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\Documents\Templates\DocumentTemplates;
use App\Support\TeamSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Invoice settings: the template of quotations, invoices and credit notes and
 * what these documents show (tax, columns, blocks, titles and formats).
 */
class InvoiceSettingsController extends Controller
{
    public function __construct(private DocumentTemplates $templates)
    {
        //
    }

    /**
     * Show the invoice settings.
     */
    public function edit(DocumentPdf $pdf): Response
    {
        $team = app(CurrentTeam::class)->team();
        $options = DocumentOptions::forTeam($team);

        return Inertia::render('company-settings/InvoiceSettings', [
            'templates' => array_map(fn (array $template) => [
                ...$template,
                'layout_label' => TemplateLayout::from($template['layout'])->label(),
            ], $this->templates->available($team)),
            'chosen' => $this->templates->chosenKeys($team),
            'options' => $options->toArray(),
            // An example invoice to draw the small pictures of the templates.
            'sample' => $pdf->sample($team, 'invoice', $options),
            'choices' => [
                'types' => self::options(DocumentTemplates::TYPES),
                'tax_columns' => self::options(DocumentOptions::TAX_COLUMNS),
                'tax_summaries' => self::options(DocumentOptions::TAX_SUMMARIES),
                'signatures' => self::options(DocumentOptions::SIGNATURES),
                'date_formats' => self::options(DocumentOptions::DATE_FORMATS),
                'number_formats' => self::options(DocumentOptions::NUMBER_FORMATS),
                'currency_displays' => self::options(DocumentOptions::CURRENCY_DISPLAYS),
                'papers' => self::options(DocumentOptions::PAPERS),
            ],
            'salesTaxRegistered' => filled($team->defaultDepartment()?->strn),
        ]);
    }

    /**
     * Save the chosen templates and the document options.
     */
    public function update(Request $request): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();
        $keys = array_column($this->templates->available($team), 'key');
        $rules = DocumentOptions::rules('options.');

        foreach (array_keys(DocumentTemplates::TYPES) as $type) {
            $rules["templates.{$type}"] = ['required', 'string', Rule::in($keys)];
        }

        $validated = $request->validate($rules, [
            'templates.*.in' => 'Choose one of the templates.',
        ]);

        $team->update(['settings' => TeamSettings::merge($team->settings ?? [], [
            'documents' => [
                'templates' => Arr::only($validated['templates'], array_keys(DocumentTemplates::TYPES)),
                'options' => DocumentOptions::fromArray($validated['options'])->toArray(),
            ],
        ])]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice settings saved.')]);

        return back();
    }

    /**
     * Show an example document as PDF with one of the company's templates and options that are not saved yet.
     *
     * The query holds the kind of document ("type"), a template key ("template") and optionally
     * document options ("options[...]"); anything missing or invalid falls back to what the
     * company saved.
     */
    public function preview(Request $request, DocumentPdf $pdf): HttpResponse
    {
        $team = app(CurrentTeam::class)->team();
        $type = array_key_exists((string) $request->query('type'), DocumentTemplates::TYPES) ? (string) $request->query('type') : 'invoice';

        $definition = $this->templates->definition($team, (string) $request->query('template', ''))
            ?? $this->templates->definition($team, $this->templates->chosenKey($team, $type))
            ?? TemplateLayout::Classic->definition();

        $saved = DocumentOptions::forTeam($team)->toArray();
        $options = DocumentOptions::fromArray([...$saved, ...Arr::only((array) $request->query('options', []), array_keys($saved))]);

        return response($pdf->preview($team, $type, $definition, $options), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Turn a value => label list into select options.
     *
     * @param  array<string, string>  $list
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $list): array
    {
        return array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys($list), $list);
    }
}
