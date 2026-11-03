<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\BillingMethod;
use App\Enums\Module;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Project;
use App\Models\Task;
use App\Support\CurrentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON search endpoints used by the searchable select inputs.
 */
class LookupController extends Controller
{
    /**
     * Search records of the given type.
     */
    public function __invoke(Request $request, string $type): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();

        $results = match ($type) {
            'companies' => $this->companies($request, $term),
            'contacts' => $this->contacts($request, $term),
            'products' => $this->products($request, $term),
            'projects' => $this->projects($request, $term),
            'tasks' => $this->tasks($request, $term),
            default => abort(404),
        };

        return response()->json($results);
    }

    /**
     * Search companies.
     *
     * @return array<int, array{id: int, label: string, description: string|null}>
     */
    protected function companies(Request $request, string $term): array
    {
        // Every module that links records to a customer or supplier may search companies.
        $this->authorize($request, Module::Crm, Module::Deals, Module::Quotations, Module::Invoicing, Module::Expenses);

        return Company::query()
            ->active()
            ->search($term)
            ->when($request->filled('relationship'), fn ($query) => $query->where('relationship', $request->string('relationship')->toString()))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'ntn', 'strn', 'sales_tax_registered', 'currency', 'payment_term_id'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'label' => $company->name,
                'description' => $company->email ?? ($company->ntn ? 'NTN '.$company->ntn : null),
                'data' => [
                    'name' => $company->name,
                    'ntn' => $company->ntn,
                    'strn' => $company->strn,
                    'sales_tax_registered' => $company->sales_tax_registered,
                    'currency' => $company->currency,
                    'payment_term_id' => $company->payment_term_id,
                ],
            ])
            ->all();
    }

    /**
     * Search contacts, optionally only those linked to a company.
     *
     * @return array<int, array{id: int, label: string, description: string|null}>
     */
    protected function contacts(Request $request, string $term): array
    {
        $this->authorize($request, Module::Crm, Module::Deals, Module::Quotations, Module::Invoicing, Module::Expenses);

        return Contact::query()
            ->with('companies:companies.id,companies.name')
            ->active()
            ->search($term)
            ->when($request->integer('company'), fn ($query, $companyId) => $query->whereHas('companies', fn ($companies) => $companies->whereKey($companyId)))
            ->orderBy('last_name')
            ->limit(20)
            ->get()
            ->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'label' => $contact->full_name,
                'description' => $contact->companies->pluck('name')->implode(', ') ?: $contact->email,
            ])
            ->all();
    }

    /**
     * Search active products, including the data needed to fill a document line.
     *
     * @return array<int, array{id: int, label: string, description: string|null, data: array<string, mixed>}>
     */
    protected function products(Request $request, string $term): array
    {
        $this->authorize($request, Module::Products, Module::Quotations, Module::Invoicing, Module::Projects, Module::WorkOrders);

        return Product::query()
            ->active()
            ->search($term)
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->name,
                'description' => trim(($product->code ? $product->code.' · ' : '').number_format((float) $product->sale_price, 2).' / '.$product->unit),
                'data' => [
                    'name' => $product->name,
                    'description' => $product->description,
                    'unit' => $product->unit,
                    'sale_price' => $product->sale_price,
                    'purchase_price' => $product->purchase_price,
                    'tax_rate_id' => $product->tax_rate_id,
                    'hs_code' => $product->hs_code,
                    'type' => $product->type->value,
                ],
            ])
            ->all();
    }

    /**
     * Search open projects.
     *
     * @return array<int, array{id: int, label: string, description: string|null, data: array<string, mixed>}>
     */
    protected function projects(Request $request, string $term): array
    {
        $this->authorize($request, Module::Projects, Module::TimeTracking, Module::Expenses, Module::Invoicing);

        return Project::query()
            ->with('company:id,name')
            ->open()
            ->search($term)
            ->orderBy('title')
            ->limit(20)
            ->get(['id', 'number', 'title', 'company_id', 'billing_method'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'label' => trim(($project->number ?? '').' '.$project->title),
                'description' => $project->company?->name,
                'data' => [
                    'company_id' => $project->company_id,
                    'billable' => $project->billing_method !== BillingMethod::NonBillable,
                ],
            ])
            ->all();
    }

    /**
     * Search open tasks (of one project, when given).
     *
     * @return array<int, array{id: int, label: string, description: string|null, data: array<string, mixed>}>
     */
    protected function tasks(Request $request, string $term): array
    {
        $this->authorize($request, Module::Projects, Module::TimeTracking);

        return Task::query()
            ->with('project:id,title')
            ->open()
            ->search($term)
            ->when($request->integer('project'), fn ($query, $project) => $query->where('project_id', $project))
            ->orderBy('title')
            ->limit(20)
            ->get(['id', 'title', 'project_id', 'work_type_id', 'is_billable'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'label' => $task->title,
                'description' => $task->project?->title,
                'data' => [
                    'project_id' => $task->project_id,
                    'work_type_id' => $task->work_type_id,
                    'billable' => $task->is_billable,
                ],
            ])
            ->all();
    }

    /**
     * Ensure the user can view at least one of the modules.
     */
    protected function authorize(Request $request, Module ...$modules): void
    {
        $team = app(CurrentTeam::class)->team();

        foreach ($modules as $module) {
            if ($request->user()->canAccessModule($team, $module, AccessLevel::Read)) {
                return;
            }
        }

        abort(403);
    }
}
