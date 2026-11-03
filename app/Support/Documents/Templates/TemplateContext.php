<?php

namespace App\Support\Documents\Templates;

use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\Storage;

/**
 * What the elements of a template print for one document: whether an element
 * has anything to show (and is allowed by the invoice settings), the values of
 * text placeholders and the uploaded images of the company.
 */
final class TemplateContext
{
    /**
     * Placeholders of the text element and what they stand for.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        '{company_name}' => 'Your company name',
        '{document_title}' => 'The title, e.g. Invoice',
        '{document_number}' => 'The number of the document',
        '{document_date}' => 'The date of the document',
        '{due_date}' => 'The due date (valid until on quotations)',
        '{customer_name}' => 'The name of the customer',
        '{total}' => 'The total with currency',
        '{amount_due}' => 'The total or the balance due',
        '{currency}' => 'The currency code, e.g. PKR',
    ];

    /**
     * Data URIs of images already read.
     *
     * @var array<string, string|null>
     */
    private array $images = [];

    /**
     * @param  array<string, mixed>  $data  the data of the document (see DocumentPdf::data())
     */
    public function __construct(private array $data)
    {
        //
    }

    /**
     * Determine if an element has something to show on this document.
     *
     * @param  array{id: string, type: string, settings: array<string, mixed>, style: array<string, mixed>}  $element
     */
    public function visible(array $element): bool
    {
        $settings = $element['settings'];
        $data = $this->data;
        /** @var DocumentOptions $options */
        $options = $data['options'];
        /** @var SalesDocument $document */
        $document = $data['document'];

        return match ($element['type']) {
            'title', 'customer', 'items', 'totals', 'divider', 'spacer' => true,
            'logo' => $data['logo'] !== null,
            'company' => filled($data['seller']['name']) || $data['seller']['address'] !== [],
            'stamp' => $data['stamp'] !== null,
            'details' => $data['view']['meta'] !== [],
            'extra' => $data['sideBox'] !== null,
            'heading' => ($settings['show_title'] && filled($document->getAttribute('title')))
                || ($settings['show_introduction'] && filled($document->getAttribute('introduction'))),
            'amount_due' => $this->highlight() !== null,
            'words' => $data['amountInWords'] !== null,
            'payment' => $data['payment'] !== null,
            'qr' => $data['qr'] !== null,
            'terms' => $data['view']['terms'] !== null,
            'signature' => $this->signatureLabels($settings) !== [],
            'acceptance' => $data['acceptance'] !== null,
            'closing' => $options->closingNote !== null,
            'footer_text' => $options->companyFooter && filled($data['department']?->document_footer),
            'text' => trim((string) $settings['text']) !== '',
            'image' => $this->image((string) $settings['image']) !== null,
            default => false,
        };
    }

    /**
     * Get the amount to highlight: the balance due of a part-paid invoice, otherwise the total.
     *
     * @return array{label: string, value: string}|null
     */
    public function highlight(): ?array
    {
        $highlight = null;

        foreach ($this->data['view']['totals'] as $row) {
            if ($row['style'] === 'grand' || $row['style'] === 'strong') {
                $highlight = ['label' => $row['label'], 'value' => $row['value']];
            }
        }

        return $highlight;
    }

    /**
     * Get the labels of the signature lines: from the invoice settings, or the element's own.
     * None when signature lines are switched off in the invoice settings.
     *
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function signatureLabels(array $settings): array
    {
        /** @var DocumentOptions $options */
        $options = $this->data['options'];

        if ($options->signature === 'none') {
            return [];
        }

        if ($settings['mode'] === 'custom') {
            return array_values((array) $settings['labels']);
        }

        return $options->signature === 'double' ? ['Received by', 'Authorised signature'] : ['Authorised signature'];
    }

    /**
     * Fill in the placeholders of a text.
     */
    public function placeholders(string $text): string
    {
        return strtr($text, $this->placeholderValues());
    }

    /**
     * Get the value of every placeholder.
     *
     * @return array<string, string>
     */
    public function placeholderValues(): array
    {
        /** @var SalesDocument $document */
        $document = $this->data['document'];
        /** @var DocumentOptions $options */
        $options = $this->data['options'];

        $due = match (true) {
            $document instanceof Invoice => $document->due_date,
            $document instanceof Quotation => $document->valid_until,
            default => null,
        };

        return [
            '{company_name}' => (string) $this->data['seller']['name'],
            '{document_title}' => (string) $this->data['title'],
            '{document_number}' => $document->number ?? 'DRAFT',
            '{document_date}' => (string) $options->date($document->date),
            '{due_date}' => (string) $options->date($due),
            '{customer_name}' => (string) $this->data['customer']['name'],
            '{total}' => $options->money($document->total, $document->currency),
            '{amount_due}' => $this->highlight()['value'] ?? '',
            '{currency}' => $document->currency,
        ];
    }

    /**
     * Get an uploaded image of the company as a data URI (null when it does not exist).
     */
    public function image(string $name): ?string
    {
        if (array_key_exists($name, $this->images)) {
            return $this->images[$name];
        }

        $path = self::imagePath((int) $this->data['document']->team_id, $name);
        $disk = Storage::disk('local');

        if ($path === null || ! $disk->exists($path)) {
            return $this->images[$name] = null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return $this->images[$name] = 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * Get the storage path of an uploaded template image of a team (null for an invalid name).
     */
    public static function imagePath(int $teamId, string $name): ?string
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}\.(png|jpe?g|gif)$/', $name) !== 1) {
            return null;
        }

        return "teams/{$teamId}/template-images/{$name}";
    }
}
