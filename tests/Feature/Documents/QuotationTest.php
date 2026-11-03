<?php

use App\Enums\DealStatus;
use App\Enums\QuotationStatus;
use App\Enums\TeamRole;
use App\Mail\DocumentMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealPhase;
use App\Models\DealPipeline;
use App\Models\Quotation;
use App\Models\SentEmail;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

// The examples use fixed dates in fiscal year 2026-27.
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * A drawn signature: a valid 1×1 PNG as a data URI.
 */
function signaturePng(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
}

/**
 * A valid quotation form submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quotationPayload(Team $team, ?Company $company, array $overrides = []): array
{
    return array_merge([
        'company_id' => $company?->id,
        'title' => 'Office furniture',
        'date' => '2026-09-30',
        'valid_until' => '2026-10-30',
        'currency' => 'PKR',
        'exchange_rate' => '1',
        'discount_percent' => '0',
        'apply_further_tax' => false,
        'lines' => [
            ['type' => 'section', 'description' => 'Furniture'],
            ['type' => 'item', 'description' => 'Office chair', 'quantity' => '10', 'unit' => 'pcs', 'unit_price' => '25000', 'discount_percent' => '0', 'tax_rate_id' => salesTax($team)->id],
            ['type' => 'item', 'description' => 'Installation', 'quantity' => '1', 'unit' => 'job', 'unit_price' => '5000', 'discount_percent' => '0', 'tax_rate_id' => null],
        ],
    ], $overrides);
}

/**
 * Create a quotation through the form and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function createQuotation($test, User $user, Team $team, ?Company $company, array $overrides = []): Quotation
{
    $test->actingAs($user)
        ->post(route('quotations.store', ['current_team' => $team->slug]), quotationPayload($team, $company, $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return Quotation::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

test('a quotation is created with numbered lines, totals and a customer snapshot', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create([
        'name' => 'Indus Traders',
        'ntn' => '1234567-8',
        'sales_tax_registered' => true,
        'email' => 'accounts@indus.test',
    ]);

    $quotation = createQuotation($this, $user, $team, $company);

    expect($quotation->number)->toBe('QT-2627-0001')
        ->and($quotation->status)->toBe(QuotationStatus::Draft)
        ->and($quotation->customer_name)->toBe('Indus Traders')
        ->and($quotation->customer_ntn)->toBe('1234567-8')
        ->and($quotation->customer_email)->toBe('accounts@indus.test')
        ->and($quotation->subtotal)->toBe('255000.00')
        ->and($quotation->tax_total)->toBe('45000.00')
        ->and($quotation->further_tax_amount)->toBe('0.00')
        ->and($quotation->total)->toBe('300000.00')
        ->and($quotation->created_by)->toBe($user->id);

    $lines = $quotation->lines()->get();

    expect($lines)->toHaveCount(3)
        ->and($lines[1]->tax_rate)->toBe('18.000')
        ->and($lines[1]->tax_name)->toBe('Sales tax 18%')
        ->and($lines[1]->total)->toBe('295000.00')
        ->and($lines[2]->total)->toBe('5000.00');
});

test('quotation numbers follow each other', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();

    $first = createQuotation($this, $user, $team, $company);
    $second = createQuotation($this, $user, $team, $company);

    expect($first->number)->toBe('QT-2627-0001')
        ->and($second->number)->toBe('QT-2627-0002');
});

test('further tax is added for customers not registered for sales tax', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['sales_tax_registered' => false]);

    $quotation = createQuotation($this, $user, $team, $company, ['apply_further_tax' => true]);

    expect($quotation->further_tax_rate)->toBe('4.000')
        ->and($quotation->further_tax_amount)->toBe('10000.00')
        ->and($quotation->total)->toBe('310000.00');
});

test('a quotation needs a customer and a priced line', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('quotations.store', ['current_team' => $team->slug]), quotationPayload($team, null, [
            'lines' => [['type' => 'text', 'description' => 'Just a remark']],
        ]))
        ->assertSessionHasErrors(['company_id', 'lines']);
});

test('lines cannot use another team\'s tax rate', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $company = Company::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('quotations.store', ['current_team' => $team->slug]), quotationPayload($team, $company, [
            'lines' => [['type' => 'item', 'description' => 'Chair', 'quantity' => '1', 'unit_price' => '10', 'tax_rate_id' => salesTax($otherTeam)->id]],
        ]))
        ->assertSessionHasErrors('lines.0.tax_rate_id');
});

test('the create form is prefilled from a deal', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['sales_tax_registered' => false]);
    $pipeline = DealPipeline::withoutGlobalScopes()->where('team_id', $team->id)->firstOrFail();
    $phase = DealPhase::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->orderBy('sort')->firstOrFail();
    $deal = Deal::factory()->for($team)->create([
        'title' => 'Fit-out Lahore office',
        'company_id' => $company->id,
        'pipeline_id' => $pipeline->id,
        'phase_id' => $phase->id,
    ]);

    $this->actingAs($user)
        ->get(route('quotations.create', ['current_team' => $team->slug, 'deal' => $deal->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Create')
            ->where('prefill.deal.id', $deal->id)
            ->where('prefill.company.id', $company->id)
            ->where('prefill.title', 'Fit-out Lahore office')
            ->where('prefill.apply_further_tax', true));
});

test('a draft quotation can be updated and its lines replaced', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);

    $this->actingAs($user)
        ->put(route('quotations.update', ['current_team' => $team->slug, 'quotation' => $quotation->id]), quotationPayload($team, $company, [
            'title' => 'Chairs only',
            'lines' => [['type' => 'item', 'description' => 'Office chair', 'quantity' => '2', 'unit_price' => '25000', 'tax_rate_id' => salesTax($team)->id]],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $quotation->refresh();

    expect($quotation->title)->toBe('Chairs only')
        ->and($quotation->number)->toBe('QT-2627-0001')
        ->and($quotation->lines()->count())->toBe(1)
        ->and($quotation->total)->toBe('59000.00');
});

test('an accepted quotation can no longer be edited', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);
    $quotation->forceFill(['status' => QuotationStatus::Accepted, 'accepted_at' => now()])->save();

    $this->actingAs($user)
        ->put(route('quotations.update', ['current_team' => $team->slug, 'quotation' => $quotation->id]), quotationPayload($team, $company))
        ->assertSessionHasErrors('quotation');
});

test('the quotation page and pdf can be viewed', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);

    $this->actingAs($user)
        ->get(route('quotations.show', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Show')
            ->where('quotation.number', 'QT-2627-0001')
            ->has('quotation.lines', 3)
            ->has('quotation.taxes', 2)
            ->where('emailDraft.to', (string) $quotation->customer_email)
            ->where('emailDraft.subject', fn (string $subject) => str_contains($subject, 'QT-2627-0001')));

    $response = $this->actingAs($user)
        ->get(route('quotations.pdf', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

test('the quotation list can be filtered', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    createQuotation($this, $user, $team, $company, ['title' => 'Chairs']);
    $sent = createQuotation($this, $user, $team, $company, ['title' => 'Desks']);
    $sent->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('quotations.index', ['current_team' => $team->slug, 'status' => 'sent']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('quotations/Index')
            ->has('quotations.data', 1)
            ->where('quotations.data.0.title', 'Desks')
            ->where('summary.open_count', 1));
});

test('a quotation can be e-mailed to the customer', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['email' => 'buyer@indus.test']);
    $quotation = createQuotation($this, $user, $team, $company);

    $this->actingAs($user)
        ->post(route('quotations.send', ['current_team' => $team->slug, 'quotation' => $quotation->id]), [
            'to' => 'buyer@indus.test',
            'cc' => '',
            'subject' => 'Quotation QT-2627-0001',
            'body' => 'Please find our quotation attached.',
            'attach_pdf' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('buyer@indus.test'));

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($quotation->fresh()->sent_at)->not->toBeNull()
        ->and(SentEmail::withoutGlobalScopes()->where('team_id', $team->id)->where('status', 'sent')->count())->toBe(1);
});

test('invalid addresses are rejected when sending', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);

    $this->actingAs($user)
        ->post(route('quotations.send', ['current_team' => $team->slug, 'quotation' => $quotation->id]), [
            'to' => 'not-an-address',
            'subject' => 'Quotation',
            'body' => 'Hello',
        ])
        ->assertSessionHasErrors('to');

    Mail::assertNothingSent();
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Draft);
});

test('the customer can accept a quotation online and the deal is won', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $pipeline = DealPipeline::withoutGlobalScopes()->where('team_id', $team->id)->firstOrFail();
    $phase = DealPhase::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->orderBy('sort')->firstOrFail();
    $deal = Deal::factory()->for($team)->create(['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'phase_id' => $phase->id]);
    $quotation = createQuotation($this, $user, $team, $company, ['deal_id' => $deal->id]);
    $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();

    Auth::logout();

    $this->get(route('public.quotations.show', ['token' => $quotation->public_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Quotation')
            ->where('canAnswer', true)
            ->where('quotation.number', 'QT-2627-0001'));

    $this->post(route('public.quotations.accept', ['token' => $quotation->public_token]), [
        'name' => 'Ali Raza',
        'signature' => signaturePng(),
        'agree' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $quotation->refresh();

    expect($quotation->status)->toBe(QuotationStatus::Accepted)
        ->and($quotation->accepted_by_name)->toBe('Ali Raza')
        ->and($quotation->accepted_signature)->toBe(signaturePng())
        ->and($deal->fresh()->status)->toBe(DealStatus::Won);

    // The signed PDF (with the signature image and an ACCEPTED stamp) still renders.
    $pdf = $this->get(route('public.quotations.pdf', ['token' => $quotation->public_token]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($pdf->getContent())->toStartWith('%PDF');
});

test('accepting online requires a name, a signature and consent', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);
    $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();

    $this->post(route('public.quotations.accept', ['token' => $quotation->public_token]), [
        'name' => '',
        'signature' => 'javascript:alert(1)',
    ])->assertSessionHasErrors(['name', 'signature', 'agree']);

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
});

test('the signature must be a real png image', function (string $signature) {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);
    $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();

    $this->post(route('public.quotations.accept', ['token' => $quotation->public_token]), [
        'name' => 'Ali Raza',
        'signature' => $signature,
        'agree' => true,
    ])->assertSessionHasErrors('signature');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
})->with([
    'not base64' => 'data:image/png;base64,***',
    'not a png' => 'data:image/png;base64,'.base64_encode('<svg onload="alert(1)"/>'),
    'png header only' => 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01garbage"),
    'another type' => 'data:image/jpeg;base64,/9j/4AAQSkZJRg==',
]);

test('the customer can decline a quotation online', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);
    $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();

    $this->post(route('public.quotations.decline', ['token' => $quotation->public_token]), ['reason' => 'Too expensive'])
        ->assertRedirect();

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Declined)
        ->and($quotation->fresh()->decline_reason)->toBe('Too expensive');
});

test('draft quotations are not visible to customers', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);

    Auth::logout();

    $this->get(route('public.quotations.show', ['token' => $quotation->public_token]))->assertNotFound();
    $this->get(route('public.quotations.show', ['token' => 'wrong-token']))->assertNotFound();
});

test('an expired quotation can no longer be accepted', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);
    $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now(), 'valid_until' => now()->subDays(2)])->save();

    $this->post(route('public.quotations.accept', ['token' => $quotation->public_token]), [
        'name' => 'Ali Raza',
        'signature' => signaturePng(),
        'agree' => true,
    ])->assertSessionHasErrors('quotation');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($quotation->fresh()->displayStatus()['label'])->toBe('Expired');
});

test('a quotation can be duplicated into a new draft', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $user, $team, $company);
    $quotation->forceFill(['status' => QuotationStatus::Accepted, 'accepted_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('quotations.duplicate', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertRedirect();

    $copy = Quotation::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();

    expect($copy->id)->not->toBe($quotation->id)
        ->and($copy->number)->toBe('QT-2627-0002')
        ->and($copy->status)->toBe(QuotationStatus::Draft)
        ->and($copy->public_token)->not->toBe($quotation->public_token)
        ->and($copy->lines()->count())->toBe(3)
        ->and($copy->total)->toBe($quotation->total);
});

test('quotations of other teams cannot be reached', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $company = Company::factory()->for($otherTeam)->create();
    $foreign = createQuotation($this, $otherUser, $otherTeam, $company);

    $this->actingAs($user)
        ->get(route('quotations.show', ['current_team' => $team->slug, 'quotation' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('quotations.pdf', ['current_team' => $otherTeam->slug, 'quotation' => $foreign->id]))
        ->assertForbidden();
});

test('members with read access cannot change quotations', function () {
    [$owner, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = createQuotation($this, $owner, $team, $company);
    $member = teamMember($team, TeamRole::Member, ['quotations' => 'read']);

    $this->actingAs($member)
        ->get(route('quotations.show', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertOk();

    $this->actingAs($member)
        ->post(route('quotations.store', ['current_team' => $team->slug]), quotationPayload($team, $company))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('quotations.destroy', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertForbidden();
});

test('company, contact and deal pages list their quotations', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();
    createQuotation($this, $user, $team, $company, ['contact_id' => $contact->id]);

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related.quotations.total', 1)
            ->where('related.quotations.items.0.number', 'QT-2627-0001'));

    $this->actingAs($user)
        ->get(route('contacts.show', ['current_team' => $team->slug, 'contact' => $contact->id]))
        ->assertInertia(fn (Assert $page) => $page->where('related.quotations.total', 1));
});

test('members without quotation access do not see related quotations', function () {
    [$owner, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    createQuotation($this, $owner, $team, $company);
    $member = teamMember($team, TeamRole::Member, ['crm' => 'read', 'quotations' => 'none']);

    $this->actingAs($member)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('related.quotations', null));
});
