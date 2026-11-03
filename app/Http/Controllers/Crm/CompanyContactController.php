<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CompanyContactRequest;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CompanyContactController extends Controller
{
    /**
     * Link an existing contact to the company.
     */
    public function store(CompanyContactRequest $request, Company $company): RedirectResponse
    {
        $company->contacts()->syncWithoutDetaching([
            (int) $request->validated('contact_id') => $this->pivot($request),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact linked.')]);

        return back();
    }

    /**
     * Update the contact's position at the company.
     */
    public function update(CompanyContactRequest $request, Company $company, Contact $contact): RedirectResponse
    {
        $company->contacts()->updateExistingPivot($contact->id, $this->pivot($request));

        return back();
    }

    /**
     * Unlink the contact from the company.
     */
    public function destroy(Company $company, Contact $contact): RedirectResponse
    {
        $company->contacts()->detach($contact->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact unlinked.')]);

        return back();
    }

    /**
     * Get the pivot attributes from the request.
     *
     * @return array{position: mixed, is_primary: bool, is_decision_maker: bool}
     */
    protected function pivot(CompanyContactRequest $request): array
    {
        return [
            'position' => $request->validated('position'),
            'is_primary' => $request->boolean('is_primary'),
            'is_decision_maker' => $request->boolean('is_decision_maker'),
        ];
    }
}
