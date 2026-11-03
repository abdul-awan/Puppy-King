<?php

use App\Enums\TeamRole;
use App\Enums\TemplateLayout;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\Templates\Css;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\Documents\Templates\ElementTypes;
use App\Support\Documents\Templates\Palette;
use App\Support\Documents\Templates\TemplateDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00'));
    Storage::fake('local');
});

/**
 * A template of rows with the given elements (each row one column unless given as [layout, cells]).
 *
 * @param  list<array<mixed>>  $rows
 * @return array<string, mixed>
 */
function builderDefinition(array $rows, array $page = []): array
{
    return [
        'page' => $page,
        'rows' => array_map(fn (array $row) => isset($row['layout'])
            ? $row
            : ['layout' => 'full', 'cells' => [['elements' => $row]]], $rows),
    ];
}

/**
 * Save an own template for the team and use it for invoices.
 *
 * @param  array<string, mixed>  $definition
 */
function useOwnTemplate($test, User $user, Team $team, array $definition, string $name = 'Built'): DocumentTemplate
{
    $test->actingAs($user)
        ->post(route('settings.invoice-templates.store', ['current_team' => $team->slug]), [
            'name' => $name, 'layout' => 'classic', 'definition' => $definition, 'use_for' => ['invoice'],
        ])
        ->assertSessionHasNoErrors();

    return DocumentTemplate::withoutGlobalScopes()->where('team_id', $team->id)->where('name', $name)->sole();
}

/**
 * Get the HTML of the PDF of an invoice.
 */
function builtInvoiceHtml(Invoice $invoice): string
{
    return app(DocumentPdf::class)->html($invoice->fresh() ?? $invoice);
}

/**
 * Store document options and templates for the team directly.
 *
 * @param  array<string, mixed>  $options
 * @param  array<string, string>  $templates
 */
function builderDocumentSettings(Team $team, array $options, array $templates = []): void
{
    $settings = $team->settings ?? [];
    $settings['documents'] = [
        'templates' => [...['invoice' => 'classic', 'quotation' => 'classic', 'credit_note' => 'classic'], ...$templates],
        'options' => [...DocumentOptions::defaults(), ...$options],
    ];
    $team->forceFill(['settings' => $settings])->save();
}

test('the builder gets everything it needs', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->get(route('settings.invoice-templates.create', ['current_team' => $team->slug, 'from' => 'blank']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('template-builder/Builder')
            ->where('values.name', 'My own template')
            ->where('values.layout', 'classic')
            ->has('values.definition.rows', 6)
            ->has('registry.types', count(ElementTypes::all()))
            ->has('registry.style.margin_top')
            ->has('registry.colors.background')
            ->has('structure.layouts.full')
            ->where('structure.max_elements', TemplateDefinition::MAX_ELEMENTS)
            ->has('presets', 16)
            ->has('samples.invoice.view.totals')
            ->has('samples.quotation.view.totals')
            ->has('samples.credit_note.sideBox')
            ->where('gates.qr', false)
            ->where('gates.words', true)
            ->has('placeholders')
            ->where('images', []));
});

test('the order made by dragging and dropping is what the document prints', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $invoice = issuedInvoice($this, $user, $team, $company);

    // Totals dragged above the items table, the title into the second column of the first row.
    useOwnTemplate($this, $user, $team, builderDefinition([
        ['layout' => '50-50', 'cells' => [
            ['elements' => [['type' => 'company']]],
            ['elements' => [['type' => 'title', 'style' => ['align' => 'right']], ['type' => 'details']]],
        ]],
        [['type' => 'totals']],
        [['type' => 'items']],
        [['type' => 'customer']],
    ]));

    $html = builtInvoiceHtml($invoice);
    $positions = array_map(fn (string $text) => strpos($html, $text), ['Indus Traders', '>Total<', '>Description<']);

    expect($positions)->each->not->toBeFalse()
        ->and(strpos($html, '>Total<'))->toBeLessThan(strpos($html, '>Description<'))
        ->and(strpos($html, '>Description<'))->toBeLessThan(strrpos($html, 'Indus Traders'));
});

test('element settings and styles reach the document', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $invoice = issuedInvoice($this, $user, $team, $company);

    useOwnTemplate($this, $user, $team, builderDefinition([
        [['type' => 'title', 'settings' => ['fit' => true], 'style' => ['background' => 'accent', 'padding_x' => 12, 'radius' => 10, 'uppercase' => true, 'font_size' => 14]]],
        [['type' => 'customer', 'settings' => ['heading' => 'Invoice to', 'heading_style' => 'bar', 'show_tax_numbers' => false]]],
        [['type' => 'items', 'settings' => ['header_style' => 'fill', 'row_style' => 'striped']]],
        [['type' => 'totals', 'settings' => ['grand_style' => 'fill', 'width' => 50]]],
        [['type' => 'divider', 'settings' => ['line' => 'dashed', 'thickness' => 2, 'color' => '#ff0000', 'width' => 40], 'style' => ['align' => 'center']]],
    ], ['accent' => '#4f46e5', 'font' => 'mono']));

    $html = builtInvoiceHtml($invoice);

    expect($html)
        ->toContain('display: inline-block')
        ->toContain('background-color: #4f46e5')
        ->toContain('border-radius: 10px')
        ->toContain('Invoice to')
        ->toContain('"DejaVu Sans Mono", monospace')
        ->toContain('border-top: 2px dashed #ff0000')
        ->toContain('width: 40%; margin-left: auto; margin-right: auto;')
        ->toContain('width: 50%;');
});

test('elements switched off in the invoice settings do not print', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $company);

    useOwnTemplate($this, $user, $team, builderDefinition([
        [['type' => 'items']],
        [['type' => 'qr'], ['type' => 'signature', 'settings' => ['mode' => 'custom', 'labels' => ['Checked by', 'Approved by']]]],
    ]));

    expect(builtInvoiceHtml($invoice))
        ->not->toContain('data:image/svg+xml')
        ->not->toContain('Checked by');

    builderDocumentSettings($team, ['qr_code' => true, 'signature' => 'single'], ['invoice' => 'custom:'.DocumentTemplate::withoutGlobalScopes()->where('team_id', $team->id)->value('id')]);

    expect(builtInvoiceHtml($invoice))
        ->toContain('data:image/svg+xml')
        ->toContain('Checked by')
        ->toContain('Approved by');
});

test('text elements fill in placeholders and print text as text', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $invoice = issuedInvoice($this, $user, $team, $company);

    useOwnTemplate($this, $user, $team, builderDefinition([
        [['type' => 'text', 'settings' => ['text' => "Please pay {total} by {due_date}.\nReference: {document_number} for {customer_name}"]]],
        [['type' => 'text', 'settings' => ['text' => '<script>alert(1)</script><b>bold</b>']]],
    ]));

    $html = builtInvoiceHtml($invoice->fresh() ?? $invoice);

    expect($html)
        ->toContain('Please pay PKR 300,000.00 by 30 Oct 2026.')
        ->toContain("Reference: {$invoice->fresh()?->number} for Indus Traders")
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;&lt;b&gt;bold&lt;/b&gt;')
        ->not->toContain('<script>');
});

test('the builder preview renders a template that is not saved', function () {
    [$user, $team] = teamOwner();
    $definition = TemplateLayout::Summit->definition()->withPage(['accent' => '#16a34a'])->toArray();

    $response = $this->actingAs($user)
        ->postJson(route('settings.invoice-templates.preview', ['current_team' => $team->slug]), ['definition' => $definition, 'type' => 'credit_note'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');

    $this->actingAs($user)
        ->postJson(route('settings.invoice-templates.preview', ['current_team' => $team->slug]), ['definition' => 'nothing'])
        ->assertUnprocessable();

    expect(DocumentTemplate::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(0);
});

test('images are uploaded for the company only and print in templates', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $company);

    $upload = $this->actingAs($user)
        ->post(route('settings.template-images.store', ['current_team' => $team->slug]), ['image' => UploadedFile::fake()->image('stamp.png', 120, 120)], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['name', 'url']);

    $name = (string) $upload->json('name');

    expect($name)->toMatch('/^[0-9a-f-]{36}\.png$/');
    Storage::disk('local')->assertExists("teams/{$team->id}/template-images/{$name}");

    $this->actingAs($user)->get(route('settings.template-images.show', ['current_team' => $team->slug, 'name' => $name]))->assertOk();
    $this->actingAs($otherUser)->get(route('settings.template-images.show', ['current_team' => $otherTeam->slug, 'name' => $name]))->assertNotFound();

    // Only PNG, JPG and GIF; no SVG (it can carry scripts).
    $this->actingAs($user)
        ->post(route('settings.template-images.store', ['current_team' => $team->slug]), [
            'image' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    useOwnTemplate($this, $user, $team, builderDefinition([
        [['type' => 'items']],
        [['type' => 'image', 'settings' => ['image' => $name, 'height' => 70]]],
        [['type' => 'image', 'settings' => ['image' => '../../etc/passwd', 'height' => 70]]],
    ]));

    $html = builtInvoiceHtml($invoice);

    expect($html)->toContain('data:image/png;base64,')
        ->toContain('max-height: 70px')
        ->not->toContain('passwd');

    // Another company that knows the name cannot print the image.
    expect(app(DocumentPdf::class)->html(issuedInvoice($this, $otherUser, $otherTeam, Company::factory()->for($otherTeam)->create()), TemplateDefinition::fromArray(builderDefinition([
        [['type' => 'image', 'settings' => ['image' => $name]]],
    ]))))->not->toContain('data:image/png;base64,');
});

test('unused images are removed a day after the last template stops using them', function () {
    [$user, $team] = teamOwner();
    $disk = Storage::disk('local');
    $folder = "teams/{$team->id}/template-images";
    $disk->put("{$folder}/aaaaaaaa-used.png", 'used');
    $disk->put("{$folder}/bbbbbbbb-old.png", 'old');
    $disk->put("{$folder}/cccccccc-new.png", 'new');
    touch($disk->path("{$folder}/aaaaaaaa-used.png"), now()->subDays(3)->getTimestamp());
    touch($disk->path("{$folder}/bbbbbbbb-old.png"), now()->subDays(3)->getTimestamp());

    useOwnTemplate($this, $user, $team, builderDefinition([
        [['type' => 'image', 'settings' => ['image' => 'aaaaaaaa-used.png']]],
    ]));

    $disk->assertExists("{$folder}/aaaaaaaa-used.png");
    $disk->assertMissing("{$folder}/bbbbbbbb-old.png");
    $disk->assertExists("{$folder}/cccccccc-new.png");
});

test('templates saved before the builder still work', function () {
    [$user, $team] = teamOwner();
    $template = withinTeam($team, fn () => DocumentTemplate::create([
        'name' => 'Old style',
        'layout' => 'modern',
        'design' => ['accent' => '#b91c1c', 'font' => 'serif', 'table_style' => 'grid', 'logo' => 'none', 'header' => 'company_right'],
    ]));

    $definition = $template->toDefinition();
    $types = array_column(iterator_to_array($definition->elements(), false), 'type');
    $items = collect(iterator_to_array($definition->elements(), false))->firstWhere('type', 'items');

    expect($definition->page->accent)->toBe('#b91c1c')
        ->and($definition->page->font)->toBe('serif')
        ->and($definition->page->decoration)->toBe('top_band')
        ->and($types)->not->toContain('logo')
        ->and($items['settings']['row_style'] ?? null)->toBe('grid');

    $this->actingAs($user)
        ->get(route('settings.invoice-templates.edit', ['current_team' => $team->slug, 'documentTemplate' => $template->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('values.definition.page.accent', '#b91c1c'));
});

test('a template only keeps what is allowed', function () {
    $definition = TemplateDefinition::fromArray([
        'page' => ['accent' => 'red;}body{display:none', 'font' => 'Comic Sans', 'label_spacing' => 99, 'decoration' => 'fireworks'],
        'rows' => [
            ['layout' => '99-1', 'id' => 'bad id!', 'style' => ['background' => 'url(evil)', 'gap' => -5, 'onclick' => 'x'], 'cells' => [
                ['elements' => [
                    ['type' => 'script', 'settings' => []],
                    ['type' => 'text', 'id' => 'same', 'settings' => ['text' => "Hi\x07 there", 'evil' => 1], 'style' => ['font_size' => 999, 'color' => 'red;x:y', 'border_color' => '#ABCDEF', 'width' => '100%']],
                    ['type' => 'divider', 'id' => 'same', 'settings' => ['thickness' => 'thick', 'line' => 'zigzag']],
                ]],
                ['elements' => [['type' => 'logo']]],
            ]],
        ],
    ]);

    $row = $definition->rows[0];
    [$text, $divider, $logo] = $row['cells'][0]['elements'];

    expect($definition->page->accent)->toBe('#1f3a5f')
        ->and($definition->page->font)->toBe('sans')
        ->and($definition->page->labelSpacing)->toBe(4.0)
        ->and($definition->page->decoration)->toBe('none')
        ->and($row['layout'])->toBe('full')
        ->and($row['id'])->not->toBe('bad id!')
        ->and($row['style'])->not->toHaveKey('onclick')
        ->and($row['style']['background'])->toBe('none')
        ->and($row['style']['gap'])->toBe(0)
        ->and(count($row['cells']))->toBe(1)
        ->and($text['settings'])->toBe(['text' => 'Hi there'])
        ->and($text['style']['font_size'])->toBe(40.0)
        ->and($text['style']['color'])->toBe('auto')
        ->and($text['style']['border_color'])->toBe('#abcdef')
        ->and($text['style'])->not->toHaveKey('width')
        ->and($divider['id'])->not->toBe($text['id'])
        ->and($divider['settings']['thickness'])->toBe(1.0)
        ->and($divider['settings']['line'])->toBe('solid')
        ->and($logo['type'])->toBe('logo');

    // Whatever is submitted, the CSS made from a template has only plain values.
    $css = new Css(new Palette('#1f3a5f'));

    foreach (iterator_to_array($definition->elements(), false) as $element) {
        expect($css->box($element['style'], null)['css'])->not->toContain('{')->not->toContain('}')->not->toContain('url(');
    }
});

test('a template has limits on rows and elements', function () {
    [$user, $team] = teamOwner();
    $element = ['type' => 'spacer'];

    $this->actingAs($user)
        ->post(route('settings.invoice-templates.store', ['current_team' => $team->slug]), [
            'name' => 'Too big', 'layout' => 'classic',
            'definition' => builderDefinition(array_fill(0, TemplateDefinition::MAX_ROWS + 1, [$element])),
        ])
        ->assertSessionHasErrors('definition.rows');

    $this->actingAs($user)
        ->post(route('settings.invoice-templates.store', ['current_team' => $team->slug]), [
            'name' => 'Too many', 'layout' => 'classic',
            'definition' => builderDefinition([array_fill(0, TemplateDefinition::MAX_ELEMENTS + 1, $element)]),
        ])
        ->assertSessionHasErrors('definition.rows');

    expect(count(TemplateDefinition::fromArray(builderDefinition([array_fill(0, 40, $element)]))->rows[0]['cells'][0]['elements']))
        ->toBe(TemplateDefinition::MAX_PER_CELL);
});

test('members with view access cannot upload images', function () {
    [, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['settings' => 'read']);

    $this->actingAs($member)
        ->post(route('settings.template-images.store', ['current_team' => $team->slug]), ['image' => UploadedFile::fake()->image('stamp.png')], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect(Storage::disk('local')->allFiles("teams/{$team->id}"))->toBe([]);
});

test('the page decoration, footer alignment and label colours of the page reach the document', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $company);
    $options = DocumentOptions::forTeam($team);

    $html = app(DocumentPdf::class)->html($invoice->fresh() ?? $invoice, TemplateDefinition::fromArray(builderDefinition(
        [[['type' => 'customer']]],
        ['decoration' => 'left_stripe', 'footer_align' => 'right', 'footer_border' => 'accent', 'accent' => '#7c3aed', 'label_color' => 'accent'],
    )), $options);

    expect($html)
        ->toContain('class="deco" style="top: -30px; left: -32px; width: 8px; height: 2000px;"')
        ->toContain('text-align: right; padding: 3px 0 0 90px; border-top: 1.5px solid #7c3aed;')
        ->toContain('text-transform: uppercase');
});
