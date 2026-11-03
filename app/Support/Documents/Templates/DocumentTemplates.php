<?php

namespace App\Support\Documents\Templates;

use App\Enums\TemplateLayout;
use App\Models\DocumentTemplate;
use App\Models\SalesDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

/**
 * The templates a company can choose for its quotations, invoices and credit
 * notes: the fifteen ready-made ones (keyed by name, e.g. "modern") and its own
 * (keyed "custom:12"). The choice per kind of document is stored in the team
 * settings under "documents.templates".
 */
final class DocumentTemplates
{
    /**
     * The kinds of documents that use templates.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'invoice' => 'Invoices',
        'quotation' => 'Quotations',
        'credit_note' => 'Credit notes',
    ];

    /**
     * Get all templates the company can choose.
     *
     * @return list<array{key: string, id: int|null, name: string, built_in: bool, layout: string, description: string|null, definition: array<string, mixed>}>
     */
    public function available(Team $team): array
    {
        $templates = [];

        foreach (TemplateLayout::cases() as $layout) {
            $templates[] = [
                'key' => $layout->value,
                'id' => null,
                'name' => $layout->label(),
                'built_in' => true,
                'layout' => $layout->value,
                'description' => $layout->description(),
                'definition' => $layout->definition()->toArray(),
            ];
        }

        foreach ($this->own($team) as $template) {
            $templates[] = [
                'key' => $template->key(),
                'id' => $template->id,
                'name' => $template->name,
                'built_in' => false,
                'layout' => $template->layout->value,
                'description' => null,
                'definition' => $template->toDefinition()->toArray(),
            ];
        }

        return $templates;
    }

    /**
     * Get the key of the template chosen for a kind of document (Classic when none or a deleted one is chosen).
     */
    public function chosenKey(Team $team, string $type): string
    {
        $key = (string) $team->teamSettings()->get("documents.templates.{$type}", TemplateLayout::Classic->value);

        return $this->exists($team, $key) ? $key : TemplateLayout::Classic->value;
    }

    /**
     * Get the keys of the templates chosen for each kind of document.
     *
     * @return array<string, string>
     */
    public function chosenKeys(Team $team): array
    {
        $keys = [];

        foreach (array_keys(self::TYPES) as $type) {
            $keys[$type] = $this->chosenKey($team, $type);
        }

        return $keys;
    }

    /**
     * Get the template chosen for a document.
     */
    public function forDocument(SalesDocument $document, Team $team): TemplateDefinition
    {
        return $this->definition($team, $this->chosenKey($team, $document->documentType()->value))
            ?? TemplateLayout::Classic->definition();
    }

    /**
     * Get a template by key, or null when the company has no such template.
     */
    public function definition(Team $team, string $key): ?TemplateDefinition
    {
        $layout = TemplateLayout::tryFrom($key);

        if ($layout !== null) {
            return $layout->definition();
        }

        return $this->find($team, $key)?->toDefinition();
    }

    /**
     * Determine if the company has a template with the key.
     */
    public function exists(Team $team, string $key): bool
    {
        return TemplateLayout::tryFrom($key) !== null || $this->find($team, $key) !== null;
    }

    /**
     * Find one of the company's own templates by key.
     */
    public function find(Team $team, string $key): ?DocumentTemplate
    {
        if (! str_starts_with($key, DocumentTemplate::KEY_PREFIX) || ! ctype_digit(substr($key, strlen(DocumentTemplate::KEY_PREFIX)))) {
            return null;
        }

        return DocumentTemplate::query()
            ->withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->find((int) substr($key, strlen(DocumentTemplate::KEY_PREFIX)));
    }

    /**
     * Get the company's own templates by name.
     *
     * @return Collection<int, DocumentTemplate>
     */
    public function own(Team $team): Collection
    {
        return DocumentTemplate::query()->withoutGlobalScopes()->where('team_id', $team->id)->orderBy('name')->get();
    }
}
