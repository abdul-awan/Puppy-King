<?php

namespace App\Support\Documents;

use App\Models\Department;
use App\Models\SalesDocument;
use App\Models\Team;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\Documents\Templates\DocumentPresenter;
use App\Support\Documents\Templates\DocumentTemplates;
use App\Support\Documents\Templates\PageStyle;
use App\Support\Documents\Templates\QrCode;
use App\Support\Documents\Templates\SampleDocument;
use App\Support\Documents\Templates\TemplateContext;
use App\Support\Documents\Templates\TemplateDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Str;

/**
 * Renders quotations, invoices and credit notes as PDF files, with the template
 * and the document options the company chose in its invoice settings.
 */
class DocumentPdf
{
    /**
     * The Blade view that renders every template.
     */
    private const VIEW = 'pdf.builder.document';

    public function __construct(
        private DocumentTemplates $templates,
        private DocumentPresenter $presenter,
        private SampleDocument $sample,
    ) {
        //
    }

    /**
     * Render the document with the company's template and options and return the PDF contents.
     */
    public function render(SalesDocument $document): string
    {
        $team = $this->team($document);

        return $this->renderWith($document, $this->templates->forDocument($document, $team), DocumentOptions::forTeam($team));
    }

    /**
     * Render the document with the given template and options.
     */
    public function renderWith(SalesDocument $document, TemplateDefinition $definition, DocumentOptions $options, bool $preview = false): string
    {
        $pdf = Pdf::loadView(self::VIEW, $this->data($document, $definition, $options, $preview))
            ->setPaper($options->paper)
            // Embed only the characters used instead of whole font files (~20× smaller PDFs).
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();

        if ($options->pageNumbers) {
            $this->addPageNumbers($pdf->getDomPDF(), $definition->page);
        }

        return $pdf->output();
    }

    /**
     * Render an example document of a kind ("invoice", "quotation" or "credit_note") to preview a template.
     */
    public function preview(Team $team, string $type, TemplateDefinition $definition, DocumentOptions $options): string
    {
        return $this->renderWith($this->sample->make($team, $type), $definition, $options, preview: true);
    }

    /**
     * Get the HTML the PDF is made from (for checks and tests).
     */
    public function html(SalesDocument $document, ?TemplateDefinition $definition = null, ?DocumentOptions $options = null): string
    {
        return view(self::VIEW, $this->data($document, $definition, $options))->render();
    }

    /**
     * Get the file name of the PDF.
     */
    public function filename(SalesDocument $document): string
    {
        return Str::slug($document->documentTitle().' '.($document->number ?? 'draft')).'.pdf';
    }

    /**
     * Get the data used by the template (and the public document pages).
     *
     * @return array<string, mixed>
     */
    public function data(SalesDocument $document, ?TemplateDefinition $definition = null, ?DocumentOptions $options = null, bool $preview = false): array
    {
        $document->loadMissing(['lines', 'department']);

        $team = $this->team($document);
        $definition ??= $this->templates->forDocument($document, $team);
        $options ??= DocumentOptions::forTeam($team);

        if ($options->productCodes) {
            $document->loadMissing('lines.product');
        }

        $department = $document->department ?? $team->defaultDepartment();
        $extra = $document->pdfExtra();
        $view = $this->presenter->present($document, $options, $extra);

        $data = [
            'document' => $document,
            'definition' => $definition,
            'options' => $options,
            'view' => $view,
            'title' => $view['title'],
            'department' => $department,
            'seller' => $this->seller($department, $options),
            'customer' => $this->customer($document, $options, $view['customer_heading']),
            'logo' => $department?->logoDataUri(),
            'stamp' => $options->statusStamp ? ($extra['stamp'] ?? null) : null,
            'payment' => $options->paymentDetails ? ($extra['payment_box'] ?? null) : null,
            'sideBox' => $extra['side_box'] ?? null,
            'acceptance' => $extra['signature'] ?? null,
            'qr' => $options->qrCode && ($preview || $document->hasPublicPage())
                ? QrCode::dataUri($preview ? url('/') : $document->publicUrl())
                : null,
            'footer' => $this->footer($department, $options, $view['title'], $document->number),
            'amountInWords' => $view['amount_in_words'],
        ];

        $data['context'] = new TemplateContext($data);

        return $data;
    }

    /**
     * Get example data of a kind of document for the template builder in the browser.
     *
     * Blocks that the invoice settings switch off are filled in anyway, so the builder can show
     * (greyed out) what such an element would look like.
     *
     * @return array<string, mixed>
     */
    public function sample(Team $team, string $type, DocumentOptions $options): array
    {
        $document = $this->sample->make($team, $type);
        $data = $this->data($document, null, $options->withBlocksOn(), preview: true);
        /** @var TemplateContext $context */
        $context = $data['context'];

        return [
            'type' => $type,
            'title' => $data['title'],
            'view' => $data['view'],
            'seller' => $data['seller'],
            'customer' => $data['customer'],
            'logo' => $data['logo'],
            'stamp' => $data['stamp'] ?? match ($type) {
                'invoice' => ['text' => 'PAID', 'color' => '#15803d'],
                'quotation' => ['text' => 'ACCEPTED', 'color' => '#15803d'],
                default => ['text' => 'DRAFT', 'color' => '#6b7280'],
            },
            'payment' => $data['payment'],
            'sideBox' => $data['sideBox'],
            'qr' => $data['qr'],
            'footer' => $data['footer'],
            'highlight' => $context->highlight(),
            'heading' => [
                'title' => $document->getAttribute('title'),
                'introduction' => $document->getAttribute('introduction'),
            ],
            'closing' => $options->closingNote,
            'companyFooter' => $data['department']?->document_footer,
            'signatureLabels' => $context->signatureLabels(['mode' => 'auto', 'labels' => []]),
            'placeholders' => $context->placeholderValues(),
        ];
    }

    /**
     * Get the company (department) details printed on the document.
     *
     * @return array{name: string|null, address: list<string>, contact: list<string>, tax: list<string>}
     */
    private function seller(?Department $department, DocumentOptions $options): array
    {
        return [
            'name' => $department?->displayName(),
            'address' => array_values($department?->addressLines() ?? []),
            'contact' => $options->companyContact ? array_values(array_filter([
                $department?->phone ? 'Tel: '.$department->phone : null,
                $department?->email,
                $department?->website,
            ])) : [],
            'tax' => $options->companyTaxNumbers ? array_values(array_filter([
                $department?->ntn ? 'NTN: '.$department->ntn : null,
                $department?->strn ? 'STRN: '.$department->strn : null,
            ])) : [],
        ];
    }

    /**
     * Get the customer details printed on the document.
     *
     * @return array{heading: string, name: string, address: string|null, tax: list<string>}
     */
    private function customer(SalesDocument $document, DocumentOptions $options, string $heading): array
    {
        return [
            'heading' => $heading,
            'name' => $document->customerLabel(),
            'address' => $document->customer_address,
            'tax' => $options->customerTaxNumbers ? array_values(array_filter([
                $document->customer_ntn ? 'NTN: '.$document->customer_ntn : null,
                $document->customer_strn ? 'STRN: '.$document->customer_strn : null,
                $document->customer_cnic ? 'CNIC: '.$document->customer_cnic : null,
            ])) : [],
        ];
    }

    /**
     * Get the text of the footer line on every page, or null when it is switched off.
     */
    private function footer(?Department $department, DocumentOptions $options, string $title, ?string $number): ?string
    {
        if (! $options->footerBar) {
            return null;
        }

        return implode(' · ', array_filter([
            $department?->displayName(),
            $options->companyTaxNumbers && $department?->ntn ? 'NTN '.$department->ntn : null,
            $options->companyTaxNumbers && $department?->strn ? 'STRN '.$department->strn : null,
            trim($title.' '.($number ?? '')),
        ]));
    }

    /**
     * Print "Page x of y" at the bottom of every page of documents with more than one page,
     * on the other side than the footer text.
     */
    private function addPageNumbers(Dompdf $dompdf, PageStyle $page): void
    {
        [, $right, $bottom, $left] = $page->pageMargins();
        $family = $page->fontName();
        $onLeft = $page->footerAlign === 'right';

        $dompdf->getCanvas()->page_script(function (int $number, int $pages, Canvas $canvas, FontMetrics $metrics) use ($right, $bottom, $left, $family, $onLeft) {
            if ($pages < 2) {
                return;
            }

            $font = $metrics->getFont($family);
            $text = "Page {$number} of {$pages}";
            // CSS pixels are 0.75 PDF points.
            $x = $onLeft ? $left * 0.75 : $canvas->get_width() - $right * 0.75 - $metrics->getTextWidth($text, $font, 6.5);
            $y = $canvas->get_height() - ($bottom * 0.75) + 14;

            $canvas->text($x, $y, $text, $font, 6.5, [0.55, 0.57, 0.6]);
        });
    }

    /**
     * Get the team of the document.
     */
    private function team(SalesDocument $document): Team
    {
        return $document->relationLoaded('team') && $document->getRelation('team') instanceof Team
            ? $document->getRelation('team')
            : $document->team()->firstOrFail();
    }
}
