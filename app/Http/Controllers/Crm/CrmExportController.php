<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmExportController extends Controller
{
    /**
     * Download the companies as a CSV file.
     */
    public function companies(Request $request): StreamedResponse
    {
        $query = Company::query()
            ->with(['tags', 'addresses', 'responsibleUser:id,name'])
            ->search($request->string('search')->toString())
            ->orderBy('name');

        return response()->streamDownload(function () use ($query) {
            $out = $this->open();

            $this->row($out, ['Name', 'Type', 'Business type', 'NTN', 'STRN', 'CNIC', 'Sales tax registered', 'Email', 'Phone', 'Mobile', 'Website', 'Industry', 'Address', 'City', 'Province', 'Account manager', 'Tags', 'Created']);

            $query->chunk(500, function (Collection $companies) use ($out) {
                foreach ($companies as $company) {
                    /** @var Company $company */
                    $address = $company->addressFor();

                    $this->row($out, [
                        $company->name,
                        $company->relationship->label(),
                        $company->business_type?->label(),
                        $company->ntn,
                        $company->strn,
                        $company->cnic,
                        $company->sales_tax_registered ? 'Yes' : 'No',
                        $company->email,
                        $company->phone,
                        $company->mobile,
                        $company->website,
                        $company->industry,
                        $address ? trim($address->line_1.' '.$address->line_2) : null,
                        $address?->city,
                        $address?->province?->label(),
                        $company->responsibleUser?->name,
                        $company->tags->pluck('name')->implode(', '),
                        $company->created_at?->toDateString(),
                    ]);
                }
            });

            fclose($out);
        }, 'companies-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Download the contacts as a CSV file.
     */
    public function contacts(Request $request): StreamedResponse
    {
        $query = Contact::query()
            ->with(['tags', 'companies:companies.id,companies.name'])
            ->search($request->string('search')->toString())
            ->orderBy('last_name');

        return response()->streamDownload(function () use ($query) {
            $out = $this->open();

            $this->row($out, ['First name', 'Last name', 'Email', 'Phone', 'Mobile', 'CNIC', 'Companies', 'Position', 'Marketing consent', 'Tags', 'Created']);

            $query->chunk(500, function (Collection $contacts) use ($out) {
                foreach ($contacts as $contact) {
                    /** @var Contact $contact */
                    $this->row($out, [
                        $contact->first_name,
                        $contact->last_name,
                        $contact->email,
                        $contact->phone,
                        $contact->mobile,
                        $contact->cnic,
                        $contact->companies->pluck('name')->implode(', '),
                        $contact->companies->first()?->getRelation('pivot')->getAttribute('position'),
                        $contact->marketing_consent ? 'Yes' : 'No',
                        $contact->tags->pluck('name')->implode(', '),
                        $contact->created_at?->toDateString(),
                    ]);
                }
            });

            fclose($out);
        }, 'contacts-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Open the output stream and write a UTF-8 byte order mark so Excel reads it correctly.
     *
     * @return resource
     */
    protected function open()
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            abort(500);
        }

        fwrite($out, "\xEF\xBB\xBF");

        return $out;
    }

    /**
     * Write a CSV row.
     *
     * @param  resource  $out
     * @param  array<int, mixed>  $values
     */
    protected function row($out, array $values): void
    {
        fputcsv($out, array_map(fn ($value) => $value === null ? '' : (string) $value, $values), ',', '"', '');
    }
}
