<?php

namespace App\Http\Controllers\Crm;

use App\Enums\AddressType;
use App\Enums\CompanyRelationship;
use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CrmImportController extends Controller
{
    /**
     * Maximum number of rows processed per file.
     */
    protected const MAX_ROWS = 5000;

    /**
     * Header aliases mapped to the internal column names.
     *
     * @var array<string, string>
     */
    protected const ALIASES = [
        'company' => 'name', 'company_name' => 'name', 'organisation' => 'name', 'organization' => 'name',
        'type' => 'relationship', 'relation' => 'relationship', 'customer_type' => 'relationship',
        'e_mail' => 'email', 'email_address' => 'email',
        'telephone' => 'phone', 'phone_number' => 'phone', 'tel' => 'phone',
        'cell' => 'mobile', 'mobile_number' => 'mobile', 'cell_phone' => 'mobile',
        'web' => 'website', 'url' => 'website',
        'stn' => 'strn', 'sales_tax_number' => 'strn', 'sales_tax_registration_number' => 'strn',
        'national_tax_number' => 'ntn',
        'address' => 'line_1', 'address_line_1' => 'line_1', 'street' => 'line_1', 'address_line_2' => 'line_2',
        'state' => 'province', 'region' => 'province',
        'zip' => 'postal_code', 'zip_code' => 'postal_code', 'postcode' => 'postal_code',
        'firstname' => 'first_name', 'given_name' => 'first_name',
        'lastname' => 'last_name', 'surname' => 'last_name', 'family_name' => 'last_name',
        'full_name' => 'contact_name', 'contact' => 'contact_name',
        'job_title' => 'position', 'title' => 'position', 'designation' => 'position',
    ];

    /**
     * Show the import page.
     */
    public function create(): Response
    {
        return Inertia::render('crm/Import', ['result' => null]);
    }

    /**
     * Import companies or contacts from an uploaded CSV file.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['companies', 'contacts'])],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows = $this->readCsv((string) $request->file('file')?->getRealPath());

        $result = $validated['type'] === 'companies'
            ? $this->importCompanies($rows)
            : $this->importContacts($rows);

        return Inertia::render('crm/Import', ['result' => $result]);
    }

    /**
     * Read the CSV file into associative rows keyed by normalised header.
     *
     * @return array<int, array<string, string|null>>
     */
    protected function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $firstLine = (string) fgets($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normaliseHeader((string) $header), $data);

                continue;
            }

            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $values = array_pad(array_slice($data, 0, count($headers)), count($headers), null);
            $rows[] = array_combine($headers, array_map(fn ($value) => $value === null ? null : trim((string) $value), $values));

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Import company rows.
     *
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{type: string, total: int, created: int, skipped: int, messages: array<int, string>}
     */
    protected function importCompanies(array $rows): array
    {
        $created = 0;
        $messages = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $name = (string) ($row['name'] ?? '');

            if ($name === '') {
                $messages[] = "Row {$line}: skipped, the company name is empty.";

                continue;
            }

            if (Company::query()->where('name', $name)->exists()) {
                $messages[] = "Row {$line}: skipped, \"{$name}\" already exists.";

                continue;
            }

            try {
                $company = Company::create([
                    'name' => $name,
                    'relationship' => $this->relationship($row['relationship'] ?? null),
                    'email' => $this->email($row['email'] ?? null),
                    'phone' => $this->limit($row['phone'] ?? null, 50),
                    'mobile' => $this->limit($row['mobile'] ?? null, 50),
                    'website' => $this->limit($row['website'] ?? null, 255),
                    'ntn' => $this->limit($row['ntn'] ?? null, 20),
                    'strn' => $this->limit($row['strn'] ?? null, 20),
                    'cnic' => $this->limit($row['cnic'] ?? null, 15),
                    'industry' => $this->limit($row['industry'] ?? null, 100),
                    'sales_tax_registered' => filled($row['strn'] ?? null),
                ]);

                $company->syncAddresses([$this->address($row)]);
                $company->syncTags($this->tagIds($row['tags'] ?? null));

                $created++;
            } catch (Throwable $e) {
                report($e);
                $messages[] = "Row {$line}: could not be imported.";
            }
        }

        return $this->result('companies', count($rows), $created, $messages);
    }

    /**
     * Import contact rows, linking or creating their companies.
     *
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{type: string, total: int, created: int, skipped: int, messages: array<int, string>}
     */
    protected function importContacts(array $rows): array
    {
        $created = 0;
        $messages = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $firstName = $row['first_name'] ?? null;
            $lastName = $row['last_name'] ?? null;

            if (blank($lastName) && filled($row['contact_name'] ?? null)) {
                $parts = preg_split('/\s+/', (string) $row['contact_name']) ?: [];
                $lastName = array_pop($parts);
                $firstName = $firstName ?: (implode(' ', $parts) ?: null);
            }

            if (blank($lastName)) {
                $messages[] = "Row {$line}: skipped, the last name is empty.";

                continue;
            }

            $email = $this->email($row['email'] ?? null);

            if ($email !== null && Contact::query()->where('email', $email)->exists()) {
                $messages[] = "Row {$line}: skipped, a contact with {$email} already exists.";

                continue;
            }

            try {
                $contact = Contact::create([
                    'first_name' => $this->limit($firstName, 100),
                    'last_name' => $this->limit($lastName, 100),
                    'email' => $email,
                    'phone' => $this->limit($row['phone'] ?? null, 50),
                    'mobile' => $this->limit($row['mobile'] ?? null, 50),
                    'cnic' => $this->limit($row['cnic'] ?? null, 15),
                ]);

                $contact->syncAddresses([$this->address($row)]);
                $contact->syncTags($this->tagIds($row['tags'] ?? null));

                if (filled($row['name'] ?? null)) {
                    $company = Company::query()->firstOrCreate(
                        ['name' => (string) $row['name']],
                        ['relationship' => CompanyRelationship::Customer],
                    );

                    $contact->companies()->attach($company->id, ['position' => $this->limit($row['position'] ?? null, 100)]);
                }

                $created++;
            } catch (Throwable $e) {
                report($e);
                $messages[] = "Row {$line}: could not be imported.";
            }
        }

        return $this->result('contacts', count($rows), $created, $messages);
    }

    /**
     * Build the result summary.
     *
     * @param  array<int, string>  $messages
     * @return array{type: string, total: int, created: int, skipped: int, messages: array<int, string>}
     */
    protected function result(string $type, int $total, int $created, array $messages): array
    {
        return [
            'type' => $type,
            'total' => $total,
            'created' => $created,
            'skipped' => $total - $created,
            'messages' => array_slice($messages, 0, 100),
        ];
    }

    /**
     * Normalise a header cell to an internal column name.
     */
    protected function normaliseHeader(string $header): string
    {
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))), '_');

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * Map a relationship cell to the enum, defaulting to customer.
     */
    protected function relationship(?string $value): CompanyRelationship
    {
        $value = strtolower(trim((string) $value));

        foreach (CompanyRelationship::cases() as $case) {
            if ($value === $case->value || $value === strtolower($case->label())) {
                return $case;
            }
        }

        return CompanyRelationship::Customer;
    }

    /**
     * Map a province cell to the enum.
     */
    protected function province(?string $value): ?Province
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        foreach (Province::cases() as $case) {
            if (in_array($value, [$case->value, strtolower($case->label())], true)) {
                return $case;
            }
        }

        return match ($value) {
            'kp', 'kpk', 'nwfp' => Province::KhyberPakhtunkhwa,
            'ict', 'islamabad' => Province::Islamabad,
            'gb' => Province::GilgitBaltistan,
            'ajk', 'azad kashmir' => Province::AzadKashmir,
            default => null,
        };
    }

    /**
     * Build an address row from the CSV columns.
     *
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    protected function address(array $row): array
    {
        return [
            'type' => AddressType::Primary->value,
            'line_1' => $this->limit($row['line_1'] ?? null, 255),
            'line_2' => $this->limit($row['line_2'] ?? null, 255),
            'city' => $this->limit($row['city'] ?? null, 100),
            'province' => $this->province($row['province'] ?? null)?->value,
            'postal_code' => $this->limit($row['postal_code'] ?? null, 20),
            'country' => $this->limit($row['country'] ?? null, 100) ?? 'Pakistan',
        ];
    }

    /**
     * Find or create the tags listed in a comma separated cell.
     *
     * @return array<int, int>
     */
    protected function tagIds(?string $value): array
    {
        $names = array_filter(array_map('trim', preg_split('/[,;|]/', (string) $value) ?: []));

        return array_values(array_map(
            fn (string $name) => Tag::query()->firstOrCreate(['name' => mb_substr($name, 0, 50)])->id,
            array_unique($names),
        ));
    }

    /**
     * Return a valid e-mail address or null.
     */
    protected function email(?string $value): ?string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : null;
    }

    /**
     * Trim a value to the column length, returning null for empty values.
     */
    protected function limit(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
