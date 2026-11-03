<?php

namespace App\Support;

use App\Contracts\Summarizable;
use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * Collects the documents linked to a company, contact or deal for its detail page.
 *
 * A group is null when the user may not see its module (so the page can hide it)
 * and left out when the document type cannot be linked that way (e.g. credit notes to deals).
 */
class RelatedDocuments
{
    /**
     * The number of documents shown per group.
     */
    public const LIMIT = 10;

    /**
     * Get the related documents grouped by type.
     *
     * @param  array<string, int>  $where  e.g. ['company_id' => 5]
     * @return array<string, array{items: list<array<string, mixed>>, total: int}|null>
     */
    public static function for(User $user, array $where): array
    {
        $team = app(CurrentTeam::class)->team();
        $related = [];

        foreach (self::groups() as $key => [$model, $module, $columns, $dateColumn]) {
            if (array_diff(array_keys($where), $columns) !== []) {
                continue;
            }

            if (! $user->canAccessModule($team, $module, AccessLevel::Read)) {
                $related[$key] = null;

                continue;
            }

            $query = $model::query()->where($where);

            $related[$key] = [
                'total' => (clone $query)->count(),
                'items' => array_values($query->latest($dateColumn)->latest('id')->limit(self::LIMIT)->get()
                    ->map(fn (Summarizable $document) => $document->toSummary())
                    ->all()),
            ];
        }

        return $related;
    }

    /**
     * The document types: model, guarding module, the columns they can be linked by and the date to sort on.
     *
     * @return array<string, array{0: class-string<Model&Summarizable>, 1: Module, 2: array<int, string>, 3: string}>
     */
    private static function groups(): array
    {
        return [
            'projects' => [Project::class, Module::Projects, ['company_id', 'contact_id', 'deal_id'], 'created_at'],
            'quotations' => [Quotation::class, Module::Quotations, ['company_id', 'contact_id', 'deal_id'], 'date'],
            'invoices' => [Invoice::class, Module::Invoicing, ['company_id', 'contact_id', 'deal_id'], 'date'],
            'credit_notes' => [CreditNote::class, Module::Invoicing, ['company_id', 'contact_id'], 'date'],
            'expenses' => [Expense::class, Module::Expenses, ['company_id'], 'date'],
            'tickets' => [Ticket::class, Module::Tickets, ['company_id', 'contact_id'], 'created_at'],
            'work_orders' => [WorkOrder::class, Module::WorkOrders, ['company_id', 'contact_id'], 'date'],
        ];
    }
}
