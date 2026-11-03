<?php

use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Mail\DocumentMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

// The examples use fixed dates in fiscal year 2026-27.
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * A valid recurring invoice form submission: a monthly retainer of 50,000 + 16% Punjab sales tax on services.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function recurringPayload(Team $team, Company $company, array $overrides = []): array
{
    return array_merge([
        'title' => 'Website maintenance',
        'company_id' => $company->id,
        'payment_term_id' => paymentTerm($team, '15 days')->id,
        'frequency' => 'monthly',
        'start_date' => '2026-10-01',
        'end_date' => null,
        'auto_issue' => false,
        'auto_send' => false,
        'is_active' => true,
        'currency' => 'PKR',
        'lines' => [
            ['type' => 'item', 'description' => 'Maintenance retainer', 'quantity' => '1', 'unit' => 'month', 'unit_price' => '50000', 'tax_rate_id' => salesTax($team)->id],
        ],
    ], $overrides);
}

/**
 * Create a recurring invoice through the form and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function createRecurring($test, $user, Team $team, Company $company, array $overrides = []): RecurringInvoice
{
    $test->actingAs($user)
        ->post(route('recurring-invoices.store', ['current_team' => $team->slug]), recurringPayload($team, $company, $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return RecurringInvoice::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

test('monthly dates are counted from the start date without drifting', function () {
    $start = CarbonImmutable::parse('2027-01-31');
    $dates = collect(range(0, 3))->map(fn (int $index) => RecurringFrequency::Monthly->occurrence($start, $index)->toDateString());

    expect($dates->all())->toBe(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'])
        ->and(RecurringFrequency::Quarterly->occurrence($start, 1)->toDateString())->toBe('2027-04-30')
        ->and(RecurringFrequency::Monthly->periodLabel(CarbonImmutable::parse('2026-10-01')))->toBe('October 2026');
});

test('a recurring invoice starts on its start date', function () {
    [$user, $team] = teamOwner();
    $profile = createRecurring($this, $user, $team, Company::factory()->for($team)->create());

    expect($profile->next_date?->toDateString())->toBe('2026-10-01')
        ->and($profile->total)->toBe('59000.00')
        ->and($profile->lines()->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('recurring-invoices.show', ['current_team' => $team->slug, 'recurring_invoice' => $profile->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recurring-invoices/Show')
            ->where('profile.status_label', 'Active')
            ->where('upcoming', ['2026-10-01', '2026-11-01', '2026-12-01', '2027-01-01', '2027-02-01', '2027-03-01']));
});

test('the scheduler creates draft invoices when they are due', function () {
    [$user, $team] = teamOwner();
    $profile = createRecurring($this, $user, $team, Company::factory()->for($team)->create(['name' => 'Indus Traders']));

    $this->artisan('invoices:generate-recurring', ['--date' => '2026-09-30'])->assertSuccessful();
    expect($profile->invoices()->count())->toBe(0);

    $this->artisan('invoices:generate-recurring', ['--date' => '2026-10-01'])->assertSuccessful();

    $invoice = Invoice::withoutGlobalScopes()->where('recurring_invoice_id', $profile->id)->sole();
    $profile->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->title)->toBe('Website maintenance — October 2026')
        ->and($invoice->date->toDateString())->toBe('2026-10-01')
        ->and($invoice->due_date?->toDateString())->toBe('2026-10-16')
        ->and($invoice->customer_name)->toBe('Indus Traders')
        ->and($invoice->total)->toBe('59000.00')
        ->and($profile->generated_count)->toBe(1)
        ->and($profile->next_date?->toDateString())->toBe('2026-11-01');

    // Running again the same day creates nothing new.
    $this->artisan('invoices:generate-recurring', ['--date' => '2026-10-01'])->assertSuccessful();
    expect($profile->invoices()->count())->toBe(1);
});

test('missed periods are caught up and the end date is respected', function () {
    [$user, $team] = teamOwner();
    $profile = createRecurring($this, $user, $team, Company::factory()->for($team)->create(), ['end_date' => '2026-12-15']);

    $this->artisan('invoices:generate-recurring', ['--date' => '2027-03-01'])->assertSuccessful();

    $profile->refresh();

    expect($profile->invoices()->orderBy('date')->pluck('date')->map->toDateString()->all())->toBe(['2026-10-01', '2026-11-01', '2026-12-01'])
        ->and($profile->next_date)->toBeNull();

    $this->actingAs($user)
        ->get(route('recurring-invoices.index', ['current_team' => $team->slug, 'status' => 'ended']))
        ->assertInertia(fn (Assert $page) => $page->has('profiles.data', 1)->where('profiles.data.0.status_label', 'Ended'));
});

test('invoices can be issued and e-mailed automatically', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $profile = createRecurring($this, $user, $team, Company::factory()->for($team)->create(['email' => 'accounts@indus.test']), [
        'auto_issue' => true,
        'auto_send' => true,
    ]);

    $this->artisan('invoices:generate-recurring', ['--date' => '2026-10-01'])->assertSuccessful();

    $invoice = Invoice::withoutGlobalScopes()->where('recurring_invoice_id', $profile->id)->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->number)->toBe('INV-2627-0001')
        ->and($invoice->sent_at)->not->toBeNull();

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('accounts@indus.test'));
});

test('the next invoice can be created by hand', function () {
    [$user, $team] = teamOwner();
    $profile = createRecurring($this, $user, $team, Company::factory()->for($team)->create());

    $this->actingAs($user)
        ->post(route('recurring-invoices.generate', ['current_team' => $team->slug, 'recurring_invoice' => $profile->id]))
        ->assertRedirect();

    expect($profile->invoices()->count())->toBe(1)
        ->and($profile->fresh()->next_date?->toDateString())->toBe('2026-11-01');
});

test('paused profiles are skipped and resuming skips the missed periods', function () {
    [$user, $team] = teamOwner();
    $profile = createRecurring($this, $user, $team, Company::factory()->for($team)->create());
    $toggle = route('recurring-invoices.toggle', ['current_team' => $team->slug, 'recurring_invoice' => $profile->id]);

    $this->actingAs($user)->post($toggle);
    expect($profile->fresh()->is_active)->toBeFalse();

    $this->artisan('invoices:generate-recurring', ['--date' => '2026-10-01'])->assertSuccessful();
    expect($profile->invoices()->count())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2027-01-15'));
    $this->actingAs($user)->post($toggle);

    expect($profile->fresh()->is_active)->toBeTrue()
        ->and($profile->fresh()->next_date?->toDateString())->toBe('2027-02-01');
});

test('changing a profile does not touch invoices created before', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $profile = createRecurring($this, $user, $team, $company);
    $this->artisan('invoices:generate-recurring', ['--date' => '2026-10-01']);

    $this->actingAs($user)
        ->put(route('recurring-invoices.update', ['current_team' => $team->slug, 'recurring_invoice' => $profile->id]), recurringPayload($team, $company, [
            'lines' => [['type' => 'item', 'description' => 'Maintenance retainer', 'quantity' => '1', 'unit_price' => '60000', 'tax_rate_id' => salesTax($team)->id]],
        ]))
        ->assertSessionHasNoErrors();

    expect($profile->fresh()->total)->toBe('70800.00')
        ->and($profile->fresh()->next_date?->toDateString())->toBe('2026-11-01')
        ->and($profile->invoices()->sole()->total)->toBe('59000.00');
});
