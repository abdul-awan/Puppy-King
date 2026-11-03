<?php

use App\Enums\DocumentType;
use App\Enums\TeamRole;
use App\Models\CustomField;
use App\Models\Department;
use App\Models\EmailTemplate;
use App\Models\PaymentTerm;
use App\Models\Tag;
use App\Models\TaxRate;
use App\Support\DocumentNumberer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can open every settings page', function () {
    [$user, $team] = teamOwner();

    $routes = [
        'settings.general.edit' => 'company-settings/General',
        'settings.departments.index' => 'company-settings/departments/Index',
        'settings.departments.create' => 'company-settings/departments/Form',
        'settings.tax-rates.index' => 'company-settings/TaxRates',
        'settings.payment-terms.index' => 'company-settings/PaymentTerms',
        'settings.numbering.edit' => 'company-settings/Numbering',
        'settings.work-types.index' => 'company-settings/WorkTypes',
        'settings.tags.index' => 'company-settings/Tags',
        'settings.custom-fields.index' => 'company-settings/CustomFields',
        'settings.email-templates.index' => 'company-settings/EmailTemplates',
        'settings.members.index' => 'company-settings/Members',
    ];

    foreach ($routes as $name => $component) {
        $this->actingAs($user)
            ->get(route($name, ['current_team' => $team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

test('regular members cannot open the company settings by default', function () {
    [, $team] = teamOwner();
    $member = teamMember($team);

    $this->actingAs($member)
        ->get(route('settings.general.edit', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('general settings can be saved', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->put(route('settings.general.update', ['current_team' => $team->slug]), [
            'currency' => 'USD',
            'fiscal_year_start_month' => 7,
            'further_tax_enabled' => true,
            'further_tax_rate' => '3.5',
            'quotation_validity_days' => 15,
            'reminders' => ['enabled' => true, 'days_after_due' => [30, 5]],
            'daily_capacity_minutes' => 450,
        ])
        ->assertRedirect();

    $settings = $team->fresh()->teamSettings();

    expect($settings->currency())->toBe('USD')
        ->and($settings->furtherTaxRate())->toBe('3.50')
        ->and($settings->get('reminders.days_after_due'))->toBe([5, 30])
        ->and($settings->get('quotation_validity_days'))->toBe(15)
        ->and($settings->get('daily_capacity_minutes'))->toBe(450);
});

test('only one tax rate is the default', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('settings.tax-rates.store', ['current_team' => $team->slug]), [
            'name' => 'Reduced 10%',
            'rate' => '10',
            'kind' => 'reduced',
            'authority' => 'fbr',
            'is_default' => true,
            'is_active' => true,
        ])
        ->assertRedirect();

    $defaults = TaxRate::withoutGlobalScopes()->where('team_id', $team->id)->where('is_default', true)->pluck('name');

    expect($defaults->all())->toBe(['Reduced 10%']);
});

test('payment terms can be managed', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('settings.payment-terms.store', ['current_team' => $team->slug]), [
            'name' => '90 days',
            'days' => 90,
            'end_of_month' => false,
            'is_default' => false,
        ])
        ->assertRedirect();

    $term = PaymentTerm::withoutGlobalScopes()->where('team_id', $team->id)->where('name', '90 days')->firstOrFail();

    $this->put(route('settings.payment-terms.update', ['current_team' => $team->slug, 'payment_term' => $term->id]), [
        'name' => '90 days EOM',
        'days' => 90,
        'end_of_month' => true,
    ])->assertRedirect();

    expect($term->fresh()->end_of_month)->toBeTrue()
        ->and($term->fresh()->dueDate(new DateTimeImmutable('2026-09-10'))->toDateString())->toBe('2026-12-29');

    $this->delete(route('settings.payment-terms.destroy', ['current_team' => $team->slug, 'payment_term' => $term->id]))
        ->assertRedirect();

    expect(PaymentTerm::withoutGlobalScopes()->find($term->id))->toBeNull();
});

test('the company profile can be updated with a logo', function () {
    Storage::fake('local');
    [$user, $team] = teamOwner();
    $department = $team->defaultDepartment();

    $this->actingAs($user)
        ->post(route('settings.departments.update', ['current_team' => $team->slug, 'department' => $department->id]), [
            '_method' => 'put',
            'name' => 'Head office',
            'legal_name' => 'Acme Traders (Pvt) Ltd',
            'ntn' => '1234567-8',
            'strn' => '3277876123456',
            'city' => 'Karachi',
            'province' => 'sindh',
            'iban' => 'pk36 scbl 0000 0011 2345 6702',
            'is_default' => true,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])
        ->assertRedirect(route('settings.departments.index', ['current_team' => $team->slug]));

    $department->refresh();

    expect($department->legal_name)->toBe('Acme Traders (Pvt) Ltd')
        ->and($department->iban)->toBe('PK36SCBL0000001123456702')
        ->and($department->logo_path)->not->toBeNull()
        ->and($department->logoDataUri())->toStartWith('data:image/png;base64,');

    $this->get(route('settings.departments.logo', ['current_team' => $team->slug, 'department' => $department->id]))
        ->assertOk();
});

test('the default company profile cannot be deleted', function () {
    [$user, $team] = teamOwner();
    $department = $team->defaultDepartment();

    $this->actingAs($user)
        ->delete(route('settings.departments.destroy', ['current_team' => $team->slug, 'department' => $department->id]))
        ->assertStatus(422);

    expect(Department::withoutGlobalScopes()->find($department->id))->not->toBeNull();
});

test('tag names are unique within a team but may repeat across teams', function () {
    [$user, $team] = teamOwner();
    [$other, $otherTeam] = teamOwner();

    $this->actingAs($user)->post(route('settings.tags.store', ['current_team' => $team->slug]), ['name' => 'VIP', 'color' => 'amber'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('settings.tags.store', ['current_team' => $team->slug]), ['name' => 'VIP', 'color' => 'red'])->assertSessionHasErrors('name');
    $this->actingAs($other)->post(route('settings.tags.store', ['current_team' => $otherTeam->slug]), ['name' => 'VIP', 'color' => 'red'])->assertSessionHasNoErrors();

    expect(Tag::withoutGlobalScopes()->where('name', 'VIP')->count())->toBe(2);
});

test('custom fields are created with a key and enforced on companies', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('settings.custom-fields.store', ['current_team' => $team->slug]), [
            'entity' => 'company',
            'type' => 'select',
            'label' => 'Customer segment',
            'options' => "Retail\nWholesale\nDistributor",
            'is_required' => true,
        ])
        ->assertRedirect();

    $field = CustomField::withoutGlobalScopes()->where('team_id', $team->id)->firstOrFail();

    expect($field->key)->toBe('customer_segment')
        ->and($field->options)->toBe(['Retail', 'Wholesale', 'Distributor']);

    $this->post(route('companies.store', ['current_team' => $team->slug]), [
        'name' => 'Missing Segment',
        'relationship' => 'customer',
    ])->assertSessionHasErrors('custom_fields.customer_segment');

    $this->post(route('companies.store', ['current_team' => $team->slug]), [
        'name' => 'Has Segment',
        'relationship' => 'customer',
        'custom_fields' => ['customer_segment' => 'Wholesale'],
    ])->assertSessionHasNoErrors();
});

test('a dropdown custom field needs options', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('settings.custom-fields.store', ['current_team' => $team->slug]), [
            'entity' => 'contact',
            'type' => 'select',
            'label' => 'Empty dropdown',
        ])
        ->assertSessionHasErrors('options');
});

test('numbering patterns and the next number can be changed', function () {
    [$user, $team] = teamOwner();

    $numbering = [];
    foreach (DocumentType::cases() as $type) {
        $numbering[$type->value] = ['pattern' => $type->defaultPattern(), 'padding' => 4, 'next_number' => null];
    }
    $numbering['invoice'] = ['pattern' => 'SI-{YYYY}-{SEQ}', 'padding' => 5, 'next_number' => 120];

    $this->actingAs($user)
        ->put(route('settings.numbering.update', ['current_team' => $team->slug]), ['numbering' => $numbering])
        ->assertRedirect();

    $next = app(DocumentNumberer::class)->next($team->fresh(), DocumentType::Invoice, now(), $team->defaultDepartment());

    expect($next)->toBe('SI-'.now()->format('Y').'-00120');
});

test('numbering patterns must contain the running number', function () {
    [$user, $team] = teamOwner();

    $numbering = [];
    foreach (DocumentType::cases() as $type) {
        $numbering[$type->value] = ['pattern' => 'NO-SEQUENCE', 'padding' => 4];
    }

    $this->actingAs($user)
        ->put(route('settings.numbering.update', ['current_team' => $team->slug]), ['numbering' => $numbering])
        ->assertSessionHasErrors('numbering.invoice.pattern');
});

test('e-mail templates can be edited', function () {
    [$user, $team] = teamOwner();
    $template = EmailTemplate::withoutGlobalScopes()->where('team_id', $team->id)->where('type', 'invoice')->firstOrFail();

    $this->actingAs($user)
        ->put(route('settings.email-templates.update', ['current_team' => $team->slug, 'emailTemplate' => $template->id]), [
            'subject' => 'Your invoice {invoice_number}',
            'body' => 'Please pay {invoice_total}.',
        ])
        ->assertRedirect();

    expect($template->fresh()->render(['{invoice_number}' => 'INV-2627-0001', '{invoice_total}' => 'Rs 1,000.00']))
        ->toBe(['subject' => 'Your invoice INV-2627-0001', 'body' => 'Please pay Rs 1,000.00.']);
});

test('admins can restrict what a member may do', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team);

    $this->actingAs($member)->get(route('companies.index', ['current_team' => $team->slug]))->assertOk();

    $this->actingAs($owner)
        ->put(route('settings.members.update', ['current_team' => $team->slug, 'user' => $member->id]), [
            'access' => ['crm' => 'none', 'invoicing' => 'read'],
            'hourly_rate' => '2500',
            'daily_capacity_minutes' => 420,
        ])
        ->assertRedirect();

    $member = $member->fresh();

    $this->actingAs($member)->get(route('companies.index', ['current_team' => $team->slug]))->assertForbidden();
    expect($member->membershipFor($team)?->hourly_rate)->toBe('2500.00');
});

test('members cannot change access rights, even with settings access', function () {
    [, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['settings' => 'write']);
    $colleague = teamMember($team);

    $this->actingAs($member)
        ->put(route('settings.members.update', ['current_team' => $team->slug, 'user' => $colleague->id]), [
            'access' => ['crm' => 'none'],
            'daily_capacity_minutes' => 480,
        ])
        ->assertForbidden();
});
