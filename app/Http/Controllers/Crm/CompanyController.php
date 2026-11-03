<?php

namespace App\Http\Controllers\Crm;

use App\Enums\AddressType;
use App\Enums\BusinessType;
use App\Enums\CompanyRelationship;
use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CompanyRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\CustomFieldResource;
use App\Models\Company;
use App\Support\RelatedDocuments;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Display a listing of the companies.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'relationship' => $request->string('relationship')->toString(),
            'tag' => $request->integer('tag') ?: null,
            'responsible' => $request->integer('responsible') ?: null,
            'archived' => $request->boolean('archived'),
            'sort' => in_array($request->input('sort'), ['name', 'created_at'], true) ? (string) $request->input('sort') : 'name',
            'direction' => $request->input('direction') === 'desc' ? 'desc' : 'asc',
        ];

        $companies = Company::query()
            ->with(['tags', 'responsibleUser:id,name', 'addresses'])
            ->withCount('contacts')
            ->search($filters['search'])
            ->when($filters['relationship'], fn ($query, $value) => $query->where('relationship', $value))
            ->when($filters['tag'], fn ($query, $value) => $query->withTag($value))
            ->when($filters['responsible'], fn ($query, $value) => $query->where('responsible_user_id', $value))
            ->when(
                $filters['archived'],
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at'),
            )
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('crm/companies/Index', [
            'companies' => CompanyResource::collection($companies),
            'filters' => $filters,
            'relationships' => CompanyRelationship::options(),
            'tags' => TeamOptions::tags(),
            'users' => TeamOptions::users(),
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        return Inertia::render('crm/companies/Create', $this->formOptions());
    }

    /**
     * Store a newly created company.
     */
    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = DB::transaction(function () use ($request) {
            $company = Company::create($request->safe()->except(['tag_ids', 'addresses']));

            $company->syncTags($request->validated('tag_ids'));
            $company->syncAddresses($request->validated('addresses'));

            return $company;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.show', $company);
    }

    /**
     * Display the company.
     */
    public function show(Request $request, Company $company): Response
    {
        $company->load([
            'tags',
            'addresses',
            'responsibleUser:id,name',
            'paymentTerm:id,name',
            'contacts' => fn ($query) => $query->orderBy('last_name'),
        ]);

        return Inertia::render('crm/companies/Show', [
            'company' => CompanyResource::make($company),
            'timeline' => Timeline::for($company),
            'attachments' => AttachmentResource::collection($company->attachments()->with('user:id,name')->get()),
            'customFields' => CustomFieldResource::collection(Company::customFieldDefinitions()),
            'related' => RelatedDocuments::for($request->user(), ['company_id' => $company->id]),
        ]);
    }

    /**
     * Show the form for editing the company.
     */
    public function edit(Company $company): Response
    {
        $company->load(['tags', 'addresses']);

        return Inertia::render('crm/companies/Edit', [
            'company' => CompanyResource::make($company),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the company.
     */
    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        DB::transaction(function () use ($request, $company) {
            $company->update($request->safe()->except(['tag_ids', 'addresses']));

            $company->syncTags($request->validated('tag_ids'));
            $company->syncAddresses($request->validated('addresses'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.show', $company);
    }

    /**
     * Archive or restore the company.
     */
    public function archive(Company $company): RedirectResponse
    {
        $archived = ! $company->isArchived();

        $company->update(['archived_at' => $archived ? now() : null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $archived ? __('Company archived.') : __('Company restored.')]);

        return back();
    }

    /**
     * Delete the company.
     */
    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('companies.index');
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'relationships' => CompanyRelationship::options(),
            'businessTypes' => BusinessType::options(),
            'provinces' => Province::options(),
            'addressTypes' => AddressType::options(),
            'currencies' => TeamOptions::currencies(),
            'users' => TeamOptions::users(),
            'paymentTerms' => TeamOptions::paymentTerms(),
            'tags' => TeamOptions::tags(),
            'customFields' => CustomFieldResource::collection(Company::customFieldDefinitions()),
        ];
    }
}
