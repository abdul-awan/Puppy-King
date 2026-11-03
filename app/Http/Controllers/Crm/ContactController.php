<?php

namespace App\Http\Controllers\Crm;

use App\Enums\AddressType;
use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ContactRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\CustomFieldResource;
use App\Models\Company;
use App\Models\Contact;
use App\Support\RelatedDocuments;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Display a listing of the contacts.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'company' => $request->integer('company') ?: null,
            'tag' => $request->integer('tag') ?: null,
            'responsible' => $request->integer('responsible') ?: null,
            'archived' => $request->boolean('archived'),
            'sort' => in_array($request->input('sort'), ['last_name', 'created_at'], true) ? (string) $request->input('sort') : 'last_name',
            'direction' => $request->input('direction') === 'desc' ? 'desc' : 'asc',
        ];

        $contacts = Contact::query()
            ->with(['tags', 'companies:companies.id,companies.name', 'responsibleUser:id,name'])
            ->search($filters['search'])
            ->when($filters['company'], fn ($query, $value) => $query->whereHas('companies', fn ($companies) => $companies->whereKey($value)))
            ->when($filters['tag'], fn ($query, $value) => $query->withTag($value))
            ->when($filters['responsible'], fn ($query, $value) => $query->where('responsible_user_id', $value))
            ->when(
                $filters['archived'],
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at'),
            )
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('crm/contacts/Index', [
            'contacts' => ContactResource::collection($contacts),
            'filters' => $filters,
            'tags' => TeamOptions::tags(),
            'users' => TeamOptions::users(),
        ]);
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(Request $request): Response
    {
        $company = $request->integer('company')
            ? Company::query()->find($request->integer('company'), ['id', 'name'])
            : null;

        return Inertia::render('crm/contacts/Create', [
            'company' => $company?->only(['id', 'name']),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a newly created contact.
     */
    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = DB::transaction(function () use ($request) {
            $contact = Contact::create($request->safe()->except(['tag_ids', 'addresses', 'companies']));

            $contact->syncTags($request->validated('tag_ids'));
            $contact->syncAddresses($request->validated('addresses'));
            $this->syncCompanies($contact, $request->validated('companies'));

            return $contact;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact created.')]);

        return to_route('contacts.show', $contact);
    }

    /**
     * Display the contact.
     */
    public function show(Request $request, Contact $contact): Response
    {
        $contact->load(['tags', 'addresses', 'responsibleUser:id,name', 'companies']);

        return Inertia::render('crm/contacts/Show', [
            'contact' => ContactResource::make($contact),
            'timeline' => Timeline::for($contact),
            'attachments' => AttachmentResource::collection($contact->attachments()->with('user:id,name')->get()),
            'customFields' => CustomFieldResource::collection(Contact::customFieldDefinitions()),
            'related' => RelatedDocuments::for($request->user(), ['contact_id' => $contact->id]),
        ]);
    }

    /**
     * Show the form for editing the contact.
     */
    public function edit(Contact $contact): Response
    {
        $contact->load(['tags', 'addresses', 'companies']);

        return Inertia::render('crm/contacts/Edit', [
            'contact' => ContactResource::make($contact),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the contact.
     */
    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        DB::transaction(function () use ($request, $contact) {
            $contact->update($request->safe()->except(['tag_ids', 'addresses', 'companies']));

            $contact->syncTags($request->validated('tag_ids'));
            $contact->syncAddresses($request->validated('addresses'));
            $this->syncCompanies($contact, $request->validated('companies'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('contacts.show', $contact);
    }

    /**
     * Archive or restore the contact.
     */
    public function archive(Contact $contact): RedirectResponse
    {
        $archived = ! $contact->isArchived();

        $contact->update(['archived_at' => $archived ? now() : null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $archived ? __('Contact archived.') : __('Contact restored.')]);

        return back();
    }

    /**
     * Delete the contact.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return to_route('contacts.index');
    }

    /**
     * Link the contact to the given companies.
     *
     * @param  array<int, array<string, mixed>>|null  $companies
     */
    protected function syncCompanies(Contact $contact, ?array $companies): void
    {
        $links = [];

        foreach ($companies ?? [] as $row) {
            $links[(int) $row['company_id']] = [
                'position' => $row['position'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'is_decision_maker' => (bool) ($row['is_decision_maker'] ?? false),
            ];
        }

        $contact->companies()->sync($links);
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'provinces' => Province::options(),
            'addressTypes' => AddressType::options(),
            'users' => TeamOptions::users(),
            'tags' => TeamOptions::tags(),
            'customFields' => CustomFieldResource::collection(Contact::customFieldDefinitions()),
        ];
    }
}
