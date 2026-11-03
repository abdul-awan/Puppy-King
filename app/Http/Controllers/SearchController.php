<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Models\Company;
use App\Models\Contact;
use App\Models\DailyExpense;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\WorkOrder;
use App\Support\CurrentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The search box in the header: finds records in every module the user may see.
 */
class SearchController extends Controller
{
    /**
     * The number of results shown per kind of record.
     */
    private const LIMIT = 5;

    /**
     * Search all modules.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $team = app(CurrentTeam::class)->team();
        $slug = $team->slug;
        $can = fn (Module $module): bool => $request->user()->canAccessModule($team, $module, AccessLevel::Read);
        $url = fn (string $route, array $parameters): string => route($route, ['current_team' => $slug, ...$parameters], false);
        $groups = [];

        if ($can(Module::Crm)) {
            $groups[] = $this->group('Companies', Company::query()->search($term)->orderBy('name')->limit(self::LIMIT)->get()
                ->map(fn (Company $company) => $this->item($company->name, $company->email ?? ($company->ntn ? "NTN {$company->ntn}" : null), $url('companies.show', ['company' => $company->id])))
                ->all());

            $groups[] = $this->group('Contacts', Contact::query()->with('companies:companies.id,companies.name')->search($term)->orderBy('last_name')->limit(self::LIMIT)->get()
                ->map(fn (Contact $contact) => $this->item($contact->full_name, $contact->companies->pluck('name')->implode(', ') ?: $contact->email, $url('contacts.show', ['contact' => $contact->id])))
                ->all());
        }

        if ($can(Module::Deals)) {
            $groups[] = $this->group('Deals', Deal::query()->with('company:id,name')->search($term)->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Deal $deal) => $this->item($deal->title, trim(($deal->company->name ?? '').' · '.$deal->currency.' '.number_format((float) $deal->value, 2), ' ·'), $url('deals.show', ['deal' => $deal->id])))
                ->all());
        }

        if ($can(Module::Quotations)) {
            $groups[] = $this->group('Quotations', Quotation::query()->search($term)->latest('date')->limit(self::LIMIT)->get()
                ->map(fn (Quotation $quotation) => $this->item(
                    trim(($quotation->number ?? 'Draft').' '.($quotation->title ?? '')),
                    $quotation->customerLabel().' · '.$quotation->displayStatus()['label'],
                    $url('quotations.show', ['quotation' => $quotation->id]),
                ))
                ->all());
        }

        if ($can(Module::Invoicing)) {
            $groups[] = $this->group('Invoices', Invoice::query()->search($term)->latest('date')->limit(self::LIMIT)->get()
                ->map(fn (Invoice $invoice) => $this->item(
                    ($invoice->number ?? 'Draft invoice').' · '.$invoice->customerLabel(),
                    $invoice->currency.' '.number_format((float) $invoice->total, 2).' · '.$invoice->displayStatus()['label'],
                    $url('invoices.show', ['invoice' => $invoice->id]),
                ))
                ->all());
        }

        if ($can(Module::Projects)) {
            $groups[] = $this->group('Projects', Project::query()->with('company:id,name')->search($term)->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Project $project) => $this->item(trim(($project->number ?? '').' '.$project->title), $project->company?->name, $url('projects.show', ['project' => $project->id])))
                ->all());

            $groups[] = $this->group('Tasks', Task::query()->with('project:id,title')->search($term)->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Task $task) => $this->item(
                    $task->title,
                    $task->project?->title,
                    $task->project_id !== null ? $url('projects.show', ['project' => $task->project_id]) : $url('tasks.index', []),
                ))
                ->all());
        }

        if ($can(Module::Tickets)) {
            $groups[] = $this->group('Tickets', Ticket::query()->with(['company:id,name', 'contact:id,first_name,last_name'])->search($term)->latest('id')->limit(self::LIMIT)->get()
                ->map(fn (Ticket $ticket) => $this->item(trim(($ticket->number ?? '').' '.$ticket->subject), $ticket->customerLabel().' · '.$ticket->status->label(), $url('tickets.show', ['ticket' => $ticket->id])))
                ->all());
        }

        if ($can(Module::WorkOrders)) {
            $groups[] = $this->group('Work orders', WorkOrder::query()->with(['company:id,name', 'contact:id,first_name,last_name'])->search($term)->latest('date')->limit(self::LIMIT)->get()
                ->map(fn (WorkOrder $workOrder) => $this->item(trim(($workOrder->number ?? '').' '.$workOrder->title), $workOrder->customerLabel().' · '.$workOrder->displayStatus()['label'], $url('work-orders.show', ['work_order' => $workOrder->id])))
                ->all());
        }

        if ($can(Module::Products)) {
            $groups[] = $this->group('Products', Product::query()->search($term)->orderBy('name')->limit(self::LIMIT)->get()
                ->map(fn (Product $product) => $this->item($product->name, trim(($product->code ? $product->code.' · ' : '').number_format((float) $product->sale_price, 2).' / '.$product->unit), $url('products.show', ['product' => $product->id])))
                ->all());
        }

        if ($can(Module::Expenses)) {
            $groups[] = $this->group('Expenses', Expense::query()->search($term)->latest('date')->limit(self::LIMIT)->get()
                ->map(fn (Expense $expense) => $this->item(trim(($expense->number ?? '').' '.$expense->title), trim(($expense->supplier_name ?? '').' · '.$expense->currency.' '.number_format((float) $expense->total, 2), ' ·'), $url('expenses.show', ['expense' => $expense->id])))
                ->all());
        }

        if ($can(Module::DailyExpenses)) {
            $groups[] = $this->group('Daily expenses', DailyExpense::query()->search($term)->latest('date')->limit(self::LIMIT)->get()
                ->map(fn (DailyExpense $expense) => $this->item(
                    $expense->description,
                    trim(($expense->number ?? '').' · '.$expense->date->format('d M Y').' · '.number_format((float) $expense->amount, 2), ' ·'),
                    $url('daily-expenses.show', ['daily_expense' => $expense->id]),
                ))
                ->all());
        }

        if ($can(Module::Payroll)) {
            $groups[] = $this->group('Employees', Employee::query()->search($term)->orderBy('name')->limit(self::LIMIT)->get()
                ->map(fn (Employee $employee) => $this->item(
                    $employee->name,
                    trim($employee->code.' · '.($employee->designation ?? ''), ' ·'),
                    $url('employees.show', ['employee' => $employee->id]),
                ))
                ->all());
        }

        return response()->json(['groups' => array_values(array_filter($groups))]);
    }

    /**
     * A group of results (left out when empty).
     *
     * @param  array<int, array{title: string, subtitle: string|null, url: string}>  $items
     * @return array{label: string, items: list<array{title: string, subtitle: string|null, url: string}>}|null
     */
    private function group(string $label, array $items): ?array
    {
        return $items === [] ? null : ['label' => $label, 'items' => array_values($items)];
    }

    /**
     * One result.
     *
     * @return array{title: string, subtitle: string|null, url: string}
     */
    private function item(string $title, ?string $subtitle, string $url): array
    {
        return ['title' => $title, 'subtitle' => $subtitle !== '' ? $subtitle : null, 'url' => $url];
    }
}
