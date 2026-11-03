<?php

use App\Enums\TeamRole;
use App\Enums\TemplateLayout;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Team;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\NumberToWords;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\Documents\Templates\DocumentTemplates;
use App\Support\Documents\Templates\SampleDocument;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * A valid invoice settings submission with the given changes.
 *
 * @param  array<string, mixed>  $options
 * @param  array<string, string>  $templates
 * @return array<string, mixed>
 */
function invoiceSettingsPayload(array $options = [], array $templates = []): array
{
    return [
        'templates' => [...['invoice' => 'classic', 'quotation' => 'classic', 'credit_note' => 'classic'], ...$templates],
        'options' => [...DocumentOptions::defaults(), ...$options],
    ];
}

/**
 * Store document options (and templates) for the team directly.
 *
 * @param  array<string, mixed>  $options
 * @param  array<string, string>  $templates
 */
function storeDocumentSettings(Team $team, array $options, array $templates = []): void
{
    $settings = $team->settings ?? [];
    $settings['documents'] = invoiceSettingsPayload($options, $templates);
    $team->forceFill(['settings' => $settings])->save();
}

/**
 * Get the HTML the PDF of the invoice is made from.
 */
function invoicePdfHtml(Invoice $invoice): string
{
    return app(DocumentPdf::class)->html($invoice->fresh() ?? $invoice);
}

/**
 * A template submission from the builder: the Modern template in red with serif type and a ruled table.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function templatePayload(array $overrides = []): array
{
    $definition = TemplateLayout::Modern->definition()
        ->withPage(['accent' => '#B91C1C', 'font' => 'serif'])
        ->mapElements(function (array $element) {
            if ($element['type'] === 'items') {
                $element['settings']['row_style'] = 'grid';
            }

            return $element;
        })
        ->toArray();

    return [
        'name' => 'Our brand',
        'layout' => 'modern',
        'definition' => $definition,
        'use_for' => [],
        ...$overrides,
    ];
}

test('the invoice settings list the fifteen ready-made templates and what is chosen', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->get(route('settings.invoice-settings.edit', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company-settings/InvoiceSettings')
            ->has('templates', 15)
            ->where('templates.0.key', 'classic')
            ->where('templates.4.key', 'elegant')
            ->where('templates.5.key', 'fresh')
            ->where('templates.14.key', 'clarity')
            ->where('templates.1.built_in', true)
            ->has('templates.0.definition.rows')
            ->where('chosen', ['invoice' => 'classic', 'quotation' => 'classic', 'credit_note' => 'classic'])
            ->where('options.show_tax', true)
            ->where('options.tax_columns', 'rate')
            ->has('sample.view.columns')
            ->has('choices.date_formats', 7));
});

test('invoice settings are saved', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->put(route('settings.invoice-settings.update', ['current_team' => $team->slug]), invoiceSettingsPayload(
            ['show_tax' => false, 'number_format' => 'south_asian', 'closing_note' => 'Thank you!', 'invoice_title' => 'Tax Invoice', 'signature' => 'double'],
            ['invoice' => 'prism', 'quotation' => 'elegant'],
        ))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $team->refresh();
    $options = DocumentOptions::forTeam($team);

    expect(app(DocumentTemplates::class)->chosenKeys($team))->toBe(['invoice' => 'prism', 'quotation' => 'elegant', 'credit_note' => 'classic'])
        ->and($options->showTax)->toBeFalse()
        ->and($options->numberFormat)->toBe('south_asian')
        ->and($options->closingNote)->toBe('Thank you!')
        ->and($options->invoiceTitle)->toBe('Tax Invoice')
        ->and($options->quotationTitle)->toBeNull()
        ->and($options->signature)->toBe('double');
});

test('invoice settings refuse unknown templates and options', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->put(route('settings.invoice-settings.update', ['current_team' => $team->slug]), invoiceSettingsPayload(
            ['tax_columns' => 'everything', 'date_format' => 'Y', 'closing_note' => str_repeat('x', 301)],
            ['invoice' => 'custom:999999', 'quotation' => 'fancy'],
        ))
        ->assertSessionHasErrors(['templates.invoice', 'templates.quotation', 'options.tax_columns', 'options.date_format', 'options.closing_note']);
});

test('members with view access can look at the invoice settings but not change them', function () {
    [, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['settings' => 'read']);
    $slug = ['current_team' => $team->slug];

    $this->actingAs($member)->get(route('settings.invoice-settings.edit', $slug))->assertOk();
    $this->actingAs($member)->get(route('settings.invoice-settings.preview', $slug))->assertOk();
    $this->actingAs($member)->put(route('settings.invoice-settings.update', $slug), invoiceSettingsPayload())->assertForbidden();
    $this->actingAs($member)->get(route('settings.invoice-templates.create', $slug))->assertForbidden();
    $this->actingAs($member)->post(route('settings.invoice-templates.store', $slug), templatePayload())->assertForbidden();
    $this->actingAs($member)->post(route('settings.invoice-templates.preview', $slug), ['definition' => templatePayload()['definition']])->assertForbidden();

    expect(DocumentTemplate::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(0);
});

test('a company makes its own template from a ready-made one and its documents use it', function () {
    [$user, $team] = teamOwner();
    $slug = ['current_team' => $team->slug];

    $this->actingAs($user)
        ->get(route('settings.invoice-templates.create', [...$slug, 'from' => 'modern']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('template-builder/Builder')
            ->where('values.name', 'My Modern template')
            ->where('values.layout', 'modern')
            ->where('values.definition.page.accent', '#0f766e')
            ->where('values.definition.page.decoration', 'top_band')
            ->has('presets', 16)
            ->has('registry.types.items')
            ->has('samples.invoice.view.columns')
            ->has('samples.credit_note.view.columns'));

    $this->actingAs($user)
        ->post(route('settings.invoice-templates.store', $slug), templatePayload(['use_for' => ['invoice', 'credit_note']]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.invoice-settings.edit', $slug));

    $template = DocumentTemplate::withoutGlobalScopes()->where('team_id', $team->id)->sole();
    $definition = $template->toDefinition();
    $items = collect(iterator_to_array($definition->elements(), false))->firstWhere('type', 'items');

    expect($template->name)->toBe('Our brand')
        ->and($template->layout)->toBe(TemplateLayout::Modern)
        ->and($template->created_by)->toBe($user->id)
        ->and($definition->page->accent)->toBe('#b91c1c')
        ->and($definition->page->font)->toBe('serif')
        ->and($items['settings']['row_style'] ?? null)->toBe('grid')
        ->and(app(DocumentTemplates::class)->chosenKeys($team->fresh()))
        ->toBe(['invoice' => "custom:{$template->id}", 'quotation' => 'classic', 'credit_note' => "custom:{$template->id}"]);

    // Copying the own template starts from it.
    $this->actingAs($user)
        ->get(route('settings.invoice-templates.create', [...$slug, 'from' => $template->key()]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('values.name', 'Our brand (copy)')
            ->where('values.definition.page.accent', '#b91c1c')
            ->where('values.layout', 'modern'));

    $company = Company::factory()->for($team)->create();
    $html = invoicePdfHtml(issuedInvoice($this, $user, $team, $company));

    expect($html)->toContain('class="deco"')
        ->toContain('#b91c1c')
        ->toContain('"DejaVu Serif", serif');
});

test('own templates are edited and deleted; documents then use the ready-made template they came from', function () {
    [$user, $team] = teamOwner();
    $slug = ['current_team' => $team->slug];
    $template = withinTeam($team, fn () => DocumentTemplate::create([
        'name' => 'Brand',
        'layout' => 'modern',
        'design' => TemplateLayout::Modern->definition()->withPage(['accent' => '#047857'])->toArray(),
    ]));
    storeDocumentSettings($team, [], ['invoice' => $template->key(), 'quotation' => $template->key()]);

    $this->actingAs($user)
        ->get(route('settings.invoice-templates.edit', [...$slug, 'documentTemplate' => $template->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('template.name', 'Brand')
            ->where('values.definition.page.accent', '#047857')
            ->where('values.use_for', ['invoice', 'quotation']));

    $this->actingAs($user)
        ->put(route('settings.invoice-templates.update', [...$slug, 'documentTemplate' => $template->id]), templatePayload([
            'name' => 'Brand 2026', 'layout' => 'corporate', 'use_for' => ['quotation'],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($template->fresh()?->name)->toBe('Brand 2026')
        ->and($template->fresh()?->layout)->toBe(TemplateLayout::Corporate)
        ->and(app(DocumentTemplates::class)->chosenKeys($team->fresh()))
        ->toBe(['invoice' => 'corporate', 'quotation' => $template->key(), 'credit_note' => 'classic']);

    $this->actingAs($user)
        ->delete(route('settings.invoice-templates.destroy', [...$slug, 'documentTemplate' => $template->id]))
        ->assertRedirect(route('settings.invoice-settings.edit', $slug));

    expect(DocumentTemplate::withoutGlobalScopes()->find($template->id))->toBeNull()
        ->and(app(DocumentTemplates::class)->chosenKeys($team->fresh()))
        ->toBe(['invoice' => 'corporate', 'quotation' => 'corporate', 'credit_note' => 'classic']);
});

test('template names are unique per company, may not be a ready-made name and a template needs rows', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $store = fn ($as, Team $for, array $overrides) => $this->actingAs($as)
        ->post(route('settings.invoice-templates.store', ['current_team' => $for->slug]), templatePayload($overrides));

    $store($user, $team, ['name' => 'Brand'])->assertSessionHasNoErrors();
    $store($user, $team, ['name' => 'Brand'])->assertSessionHasErrors('name');
    $store($user, $team, ['name' => 'Classic'])->assertSessionHasErrors('name');
    $store($user, $team, ['name' => 'Prism'])->assertSessionHasErrors('name');
    $store($user, $team, ['name' => 'Styled', 'layout' => 'fancy'])->assertSessionHasErrors('layout');
    $store($user, $team, ['name' => 'Styled', 'definition' => 'not a template'])->assertSessionHasErrors('definition');
    $store($user, $team, ['name' => 'Styled', 'definition' => ['page' => [], 'rows' => []]])->assertSessionHasErrors('definition.rows');
    $store($otherUser, $otherTeam, ['name' => 'Brand'])->assertSessionHasNoErrors();

    expect(DocumentTemplate::withoutGlobalScopes()->where('name', 'Brand')->count())->toBe(2);
});

test('a company cannot see, change or use the templates of another company', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $foreign = withinTeam($otherTeam, fn () => DocumentTemplate::create([
        'name' => 'Secret',
        'layout' => 'elegant',
        'design' => TemplateLayout::Elegant->definition()->toArray(),
    ]));
    $route = fn (string $name) => route($name, ['current_team' => $team->slug, 'documentTemplate' => $foreign->id]);

    $this->actingAs($user)->get($route('settings.invoice-templates.edit'))->assertNotFound();
    $this->actingAs($user)->put($route('settings.invoice-templates.update'), templatePayload())->assertNotFound();
    $this->actingAs($user)->delete($route('settings.invoice-templates.destroy'))->assertNotFound();
    $this->actingAs($user)
        ->put(route('settings.invoice-settings.update', ['current_team' => $team->slug]), invoiceSettingsPayload([], ['invoice' => $foreign->key()]))
        ->assertSessionHasErrors('templates.invoice');
    $this->actingAs($user)
        ->get(route('settings.invoice-templates.create', ['current_team' => $team->slug, 'from' => $foreign->key()]))
        ->assertInertia(fn (Assert $page) => $page->where('values.name', 'My Classic template'));

    // A foreign template stored by hand is ignored.
    storeDocumentSettings($team, [], ['invoice' => $foreign->key()]);

    expect(app(DocumentTemplates::class)->chosenKey($team->fresh(), 'invoice'))->toBe('classic')
        ->and(app(DocumentTemplates::class)->definition($team, $foreign->key()))->toBeNull()
        ->and($foreign->fresh()?->name)->toBe('Secret');
});

test('documents show what the invoice settings say', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders', 'ntn' => '1234567-8']);
    $invoice = issuedInvoice($this, $user, $team, $company);

    // The defaults: tax per line and per rate, international numbers.
    expect(invoicePdfHtml($invoice))
        ->toContain('Sales tax 18% on 250,000.00')
        ->toContain('45,000.00')
        ->toContain('PKR 300,000.00')
        ->toContain('NTN: 1234567-8')
        ->toContain('30 Sep 2026')
        ->toContain('Rupees three hundred thousand only');

    // Without tax: the amounts include tax and the rows still add up.
    storeDocumentSettings($team, ['show_tax' => false]);

    expect(invoicePdfHtml($invoice))
        ->not->toContain('Sales tax 18% on')
        ->toContain('Price incl. tax')
        ->toContain('29,500.00')
        ->toContain('295,000.00')
        ->toContain('PKR 300,000.00')
        ->toContain('All amounts include sales tax.');

    // FBR style columns, lakh grouping, symbols, other dates and texts.
    storeDocumentSettings($team, [
        'tax_columns' => 'amounts', 'tax_summary' => 'single', 'number_format' => 'south_asian', 'currency_display' => 'symbol',
        'date_format' => 'd/m/Y', 'customer_tax_numbers' => false, 'invoice_title' => 'Tax Invoice', 'qr_code' => true,
        'closing_note' => 'Shukriya!', 'amount_in_words' => true, 'signature' => 'double',
    ]);
    $html = invoicePdfHtml($invoice);

    expect($html)
        ->toContain('Value incl. tax')
        ->toContain('2,95,000.00')
        ->toContain('Rs 3,00,000.00')
        ->toContain('Rupees three lakh only')
        ->toContain('30/09/2026')
        ->toContain('Tax Invoice')
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('Shukriya!')
        ->toContain('Received by')
        ->not->toContain('NTN: 1234567-8')
        ->not->toContain('Sales tax 18% on');

    // Blocks switched off.
    storeDocumentSettings($team, ['item_numbers' => false, 'quantity_price' => false, 'amount_in_words' => false, 'payment_details' => false, 'footer_bar' => false]);
    $html = invoicePdfHtml($invoice);

    expect($html)
        ->not->toContain('Amount in words')
        ->not->toContain('Unit price')
        ->not->toContain('class="footer"')
        ->not->toContain('>#</th>');
});

test('all fifteen ready-made templates render quotations, invoices and credit notes', function () {
    [, $team] = teamOwner();
    $pdf = app(DocumentPdf::class);

    foreach (['invoice', 'quotation', 'credit_note'] as $type) {
        $document = app(SampleDocument::class)->make($team, $type);

        foreach (TemplateLayout::cases() as $layout) {
            $html = $pdf->html($document, $layout->definition(), new DocumentOptions(signature: 'double'));

            expect($html)->toContain('Example Traders (Pvt) Ltd')->toContain((string) $document->number)->toContain('Authorised signature');
        }
    }

    foreach ([TemplateLayout::Classic, TemplateLayout::Slate, TemplateLayout::Ledger, TemplateLayout::Prism, TemplateLayout::Clarity] as $layout) {
        expect($pdf->preview($team, 'invoice', $layout->definition(), new DocumentOptions(qrCode: true, taxColumns: 'amounts')))
            ->toStartWith('%PDF');
    }
});

test('the preview shows an example document as PDF, also with settings that are not saved', function () {
    [$user, $team] = teamOwner();
    $route = fn (array $query) => route('settings.invoice-settings.preview', ['current_team' => $team->slug, ...$query]);

    $response = $this->actingAs($user)
        ->get($route(['type' => 'quotation', 'template' => 'atlas', 'options' => ['show_tax' => '0', 'qr_code' => '1']]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');

    // Nonsense falls back to what is saved instead of failing.
    $this->actingAs($user)
        ->get($route(['type' => 'bill', 'template' => 'custom:abc', 'options' => ['paper' => 'napkin']]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($team->fresh()?->settings['documents'] ?? null)->toBeNull();
});

test('the customer page of an invoice shows it as the company chose', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $company, ['terms' => 'Pay within 30 days.']);
    storeDocumentSettings($team, ['show_tax' => false, 'terms' => false, 'company_tax_numbers' => false]);

    $this->get(route('public.invoices.show', ['token' => $invoice->public_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Document')
            ->where('presentation.tax_note', 'All amounts include sales tax.')
            ->where('presentation.terms', null)
            ->where('presentation.totals.0.label', 'Total')
            ->where('presentation.totals.0.value', 'PKR 300,000.00')
            ->where('presentation.columns', fn ($columns) => collect($columns)->pluck('key')->doesntContain('tax_rate'))
            ->where('seller.ntn', null)
            ->where('seller.strn', null));
});

test('amounts and words follow the chosen number format', function () {
    $international = new DocumentOptions;
    $southAsian = new DocumentOptions(numberFormat: 'south_asian', currencyDisplay: 'symbol');

    expect($international->money('1234567.891', 'PKR'))->toBe('PKR 1,234,567.89')
        ->and($southAsian->money('1234567.891', 'PKR'))->toBe('Rs 12,34,567.89')
        ->and($southAsian->money('-1234.5', 'USD'))->toBe('$-1,234.50')
        ->and($southAsian->money('5000', 'AED'))->toBe('AED 5,000.00')
        ->and($southAsian->number('100000'))->toBe('1,00,000.00')
        ->and($southAsian->number('999'))->toBe('999.00')
        ->and($international->money('-0.001'))->toBe('0.00')
        ->and($international->quantity('2.500'))->toBe('2.5')
        ->and($international->quantity('1500.000'))->toBe('1,500')
        ->and($international->percent('17.500'))->toBe('17.5%')
        ->and(NumberToWords::money('1234567', 'PKR', southAsian: true))->toBe('Rupees twelve lakh thirty-four thousand five hundred sixty-seven only')
        ->and(NumberToWords::money('1234567', 'PKR'))->toBe('Rupees one million two hundred thirty-four thousand five hundred sixty-seven only')
        ->and(NumberToWords::southAsian(1_500_000_000))->toBe('one hundred fifty crore')
        ->and(NumberToWords::southAsian(10_050_000))->toBe('one crore fifty thousand');
});
