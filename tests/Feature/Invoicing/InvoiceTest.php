<?php

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\TeamRole;
use App\Mail\DocumentMail;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

// The examples use fixed dates in fiscal year 2026-27.
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

test('a draft invoice has no number until it is issued', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders', 'ntn' => '1234567-8', 'sales_tax_registered' => true]);

    $invoice = createInvoice($this, $user, $team, $company);

    expect($invoice->number)->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->total)->toBe('300000.00')
        ->and($invoice->due_date?->toDateString())->toBe('2026-10-30')
        ->and($invoice->customer_ntn)->toBe('1234567-8');

    $this->actingAs($user)
        ->post(route('invoices.issue', ['current_team' => $team->slug, 'invoice' => $invoice->id]))
        ->assertRedirect(route('invoices.show', ['current_team' => $team->slug, 'invoice' => $invoice->id]));

    $invoice->refresh();

    expect($invoice->number)->toBe('INV-2627-0001')
        ->and($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->balanceDue())->toBe('300000.00');
});

test('numbers are given in the order invoices are issued', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();

    $first = createInvoice($this, $user, $team, $company);
    $second = issuedInvoice($this, $user, $team, $company);

    $this->actingAs($user)->post(route('invoices.issue', ['current_team' => $team->slug, 'invoice' => $first->id]));

    expect($second->number)->toBe('INV-2627-0001')
        ->and($first->fresh()->number)->toBe('INV-2627-0002');
});

test('an invoice cannot be issued twice', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());

    $this->actingAs($user)
        ->post(route('invoices.issue', ['current_team' => $team->slug, 'invoice' => $invoice->id]))
        ->assertSessionHasErrors('invoice');

    expect(Invoice::withoutGlobalScopes()->where('team_id', $team->id)->whereNotNull('number')->count())->toBe(1);
});

test('issued invoices cannot be edited or deleted', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $company);

    $this->actingAs($user)
        ->put(route('invoices.update', ['current_team' => $team->slug, 'invoice' => $invoice->id]), invoicePayload($team, $company, ['title' => 'Changed']))
        ->assertSessionHasErrors('invoice');

    $this->actingAs($user)
        ->delete(route('invoices.destroy', ['current_team' => $team->slug, 'invoice' => $invoice->id]))
        ->assertSessionHasErrors('invoice');

    expect($invoice->fresh()->title)->toBe('Office furniture')
        ->and($invoice->fresh()->trashed())->toBeFalse();
});

test('a draft invoice can be changed and deleted', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = createInvoice($this, $user, $team, $company);

    $this->actingAs($user)
        ->put(route('invoices.update', ['current_team' => $team->slug, 'invoice' => $invoice->id]), invoicePayload($team, $company, [
            'lines' => [['type' => 'item', 'description' => 'Desk', 'quantity' => '2', 'unit_price' => '40000', 'tax_rate_id' => salesTax($team)->id]],
        ]))
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->total)->toBe('94400.00');

    $this->actingAs($user)
        ->delete(route('invoices.destroy', ['current_team' => $team->slug, 'invoice' => $invoice->id]))
        ->assertRedirect();

    expect($invoice->fresh()->trashed())->toBeTrue();
});

test('issuing takes tracked products out of stock', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $chair = Product::factory()->for($team)->create(['name' => 'Office chair', 'track_stock' => true, 'stock_quantity' => 25]);
    $service = Product::factory()->for($team)->create(['name' => 'Installation', 'track_stock' => false, 'stock_quantity' => 0]);

    $invoice = issuedInvoice($this, $user, $team, $company, [
        'lines' => [
            ['type' => 'item', 'product_id' => $chair->id, 'description' => 'Office chair', 'quantity' => '10', 'unit_price' => '25000', 'tax_rate_id' => salesTax($team)->id],
            ['type' => 'item', 'product_id' => $service->id, 'description' => 'Installation', 'quantity' => '1', 'unit_price' => '5000'],
        ],
    ]);

    expect($chair->fresh()->stock_quantity)->toBe('15.000')
        ->and($service->fresh()->stock_quantity)->toBe('0.000');

    $movement = StockMovement::withoutGlobalScopes()->where('product_id', $chair->id)->sole();

    expect($movement->quantity)->toBe('-10.000')
        ->and($movement->balance)->toBe('15.000')
        ->and($movement->reason)->toBe("Invoice {$invoice->number}")
        ->and($movement->source_type)->toBe('invoice');
});

test('payments with tax withheld settle the invoice', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());
    $url = route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $invoice->id]);

    $this->actingAs($user)->post($url, ['date' => '2026-09-30', 'amount' => '100000', 'method' => 'cheque', 'reference' => 'CHQ 123456'])
        ->assertSessionHasNoErrors();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->displayStatus()['label'])->toBe('Partially paid')
        ->and($invoice->balanceDue())->toBe('200000.00');

    // The customer withholds 4% income tax on the value excl. tax and 1/5 of the sales tax.
    $this->actingAs($user)->post($url, [
        'date' => '2026-09-30',
        'amount' => '180800',
        'income_tax_withheld' => '10200',
        'sales_tax_withheld' => '9000',
        'method' => 'bank_transfer',
    ])->assertSessionHasNoErrors();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe('300000.00')
        ->and($invoice->balanceDue())->toBe('0.00')
        ->and($invoice->paid_at)->not->toBeNull();
});

test('a payment cannot be more than the balance or be recorded on a draft', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $draft = createInvoice($this, $user, $team, $company);
    $issued = issuedInvoice($this, $user, $team, $company);

    $this->actingAs($user)
        ->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $draft->id]), ['date' => '2026-09-30', 'amount' => '10', 'method' => 'cash'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $issued->id]), ['date' => '2026-09-30', 'amount' => '300000.01', 'method' => 'cash'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $issued->id]), ['date' => now()->addDay()->toDateString(), 'amount' => '10', 'method' => 'cash'])
        ->assertSessionHasErrors('date');

    expect($issued->payments()->count())->toBe(0);
});

test('removing a payment (e.g. a bounced cheque) reopens the invoice', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());

    $this->actingAs($user)->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $invoice->id]), [
        'date' => '2026-09-30', 'amount' => '300000', 'method' => 'cheque',
    ]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $payment = $invoice->payments()->sole();

    $this->actingAs($user)
        ->delete(route('invoices.payments.destroy', ['current_team' => $team->slug, 'invoice' => $invoice->id, 'payment' => $payment->id]))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->fresh()->paid_at)->toBeNull();
});

test('open invoices past their due date are overdue', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $company);
    $invoice->forceFill(['due_date' => now()->subDays(5)])->save();
    issuedInvoice($this, $user, $team, $company);

    expect($invoice->fresh()->isOverdue())->toBeTrue()
        ->and($invoice->fresh()->daysOverdue())->toBe(5)
        ->and($invoice->fresh()->displayStatus())->toBe(['label' => 'Overdue', 'color' => 'red']);

    $this->actingAs($user)
        ->get(route('invoices.index', ['current_team' => $team->slug, 'status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Index')
            ->has('invoices.data', 1)
            ->where('summary.overdue_count', 1)
            ->where('summary.outstanding_count', 2));
});

test('period filters include the first and the last day', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    issuedInvoice($this, $user, $team, $company, ['date' => '2026-09-30']);
    issuedInvoice($this, $user, $team, $company, ['date' => '2026-09-01']);
    issuedInvoice($this, $user, $team, $company, ['date' => '2026-08-31']);

    $this->actingAs($user)
        ->get(route('invoices.index', ['current_team' => $team->slug, 'period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 2));
});

test('an issued invoice can be credited in part', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());

    $this->actingAs($user)
        ->post(route('invoices.credit', ['current_team' => $team->slug, 'invoice' => $invoice->id]))
        ->assertRedirect();

    $creditNote = CreditNote::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole();

    expect($creditNote->status)->toBe(CreditNoteStatus::Draft)
        ->and($creditNote->total)->toBe('300000.00')
        ->and($creditNote->lines()->count())->toBe(2);

    // Only 2 of the 10 chairs came back.
    $this->actingAs($user)
        ->put(route('credit-notes.update', ['current_team' => $team->slug, 'credit_note' => $creditNote->id]), [
            'date' => '2026-09-30',
            'reason' => 'Two chairs returned damaged',
            'issue_now' => true,
            'lines' => [['type' => 'item', 'description' => 'Office chair (returned)', 'quantity' => '2', 'unit_price' => '25000', 'tax_rate_id' => salesTax($team)->id]],
        ])
        ->assertSessionHasNoErrors();

    $creditNote->refresh();
    $invoice->refresh();

    expect($creditNote->number)->toBe('CN-2627-0001')
        ->and($creditNote->status)->toBe(CreditNoteStatus::Issued)
        ->and($creditNote->total)->toBe('59000.00')
        ->and($invoice->amount_credited)->toBe('59000.00')
        ->and($invoice->balanceDue())->toBe('241000.00')
        ->and($invoice->status)->toBe(InvoiceStatus::Open);
});

test('a full credit marks the invoice as credited and returned goods go back into stock', function () {
    [$user, $team] = teamOwner();
    $chair = Product::factory()->for($team)->create(['track_stock' => true, 'stock_quantity' => 10]);
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create(), [
        'lines' => [['type' => 'item', 'product_id' => $chair->id, 'description' => 'Chair', 'quantity' => '4', 'unit_price' => '1000', 'tax_rate_id' => salesTax($team)->id]],
    ]);

    expect($chair->fresh()->stock_quantity)->toBe('6.000');

    $this->actingAs($user)->post(route('invoices.credit', ['current_team' => $team->slug, 'invoice' => $invoice->id]));
    $creditNote = CreditNote::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole();
    $creditNote->update(['restock' => true]);

    $this->actingAs($user)
        ->post(route('credit-notes.issue', ['current_team' => $team->slug, 'credit_note' => $creditNote->id]))
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Credited)
        ->and($chair->fresh()->stock_quantity)->toBe('10.000');
});

test('the credit note list shows the credited invoices', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());
    $this->actingAs($user)->post(route('invoices.credit', ['current_team' => $team->slug, 'invoice' => $invoice->id]));
    $creditNote = CreditNote::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole();
    $this->actingAs($user)->post(route('credit-notes.issue', ['current_team' => $team->slug, 'credit_note' => $creditNote->id]));

    $this->actingAs($user)
        ->get(route('credit-notes.index', ['current_team' => $team->slug, 'status' => 'issued']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('credit-notes/Index')
            ->has('creditNotes.data', 1)
            ->where('creditNotes.data.0.number', 'CN-2627-0001')
            ->where('creditNotes.data.0.invoice.number', 'INV-2627-0001')
            ->where('creditNotes.data.0.invoice.creditable_amount', '0.00'));

    $this->actingAs($user)
        ->get(route('credit-notes.index', ['current_team' => $team->slug, 'search' => 'INV-2627-0001']))
        ->assertInertia(fn (Assert $page) => $page->has('creditNotes.data', 1));
});

test('a credit note cannot be more than what is left to credit', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());

    $this->actingAs($user)->post(route('invoices.credit', ['current_team' => $team->slug, 'invoice' => $invoice->id]));
    $creditNote = CreditNote::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole();

    $this->actingAs($user)
        ->put(route('credit-notes.update', ['current_team' => $team->slug, 'credit_note' => $creditNote->id]), [
            'date' => '2026-09-30',
            'issue_now' => true,
            'lines' => [['type' => 'item', 'description' => 'Too much', 'quantity' => '1', 'unit_price' => '400000']],
        ])
        ->assertSessionHasErrors('credit_note');

    expect($creditNote->fresh()->status)->toBe(CreditNoteStatus::Draft)
        ->and($invoice->fresh()->amount_credited)->toBe('0.00');
});

test('invoice and credit note pdfs render', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());
    $this->actingAs($user)->post(route('invoices.credit', ['current_team' => $team->slug, 'invoice' => $invoice->id]));
    $creditNote = CreditNote::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole();

    foreach ([
        route('invoices.pdf', ['current_team' => $team->slug, 'invoice' => $invoice->id]),
        route('credit-notes.pdf', ['current_team' => $team->slug, 'credit_note' => $creditNote->id]),
    ] as $url) {
        $response = $this->actingAs($user)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF');
    }
});

test('the invoice page shows payments, credit notes and e-mail drafts', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['email' => 'accounts@indus.test']);
    $invoice = issuedInvoice($this, $user, $team, $company);

    $this->actingAs($user)
        ->get(route('invoices.show', ['current_team' => $team->slug, 'invoice' => $invoice->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Show')
            ->where('invoice.number', 'INV-2627-0001')
            ->where('invoice.balance_due', '300000.00')
            ->has('invoice.lines', 2)
            ->has('invoice.payments', 0)
            ->where('emailDraft.to', 'accounts@indus.test')
            ->where('emailDraft.subject', fn (string $subject) => str_contains($subject, 'INV-2627-0001'))
            ->where('reminderDraft.to', 'accounts@indus.test')
            ->has('paymentMethods', 6));
});

test('an issued invoice can be e-mailed but a draft cannot', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['email' => 'buyer@indus.test']);
    $draft = createInvoice($this, $user, $team, $company);
    $issued = issuedInvoice($this, $user, $team, $company);
    $message = ['to' => 'buyer@indus.test', 'subject' => 'Your invoice', 'body' => 'Please find the invoice attached.', 'attach_pdf' => true];

    $this->actingAs($user)
        ->post(route('invoices.send', ['current_team' => $team->slug, 'invoice' => $draft->id]), $message)
        ->assertSessionHasErrors('invoice');

    $this->actingAs($user)
        ->post(route('invoices.send', ['current_team' => $team->slug, 'invoice' => $issued->id]), $message)
        ->assertSessionHasNoErrors();

    Mail::assertSent(DocumentMail::class, 1);
    expect($issued->fresh()->sent_at)->not->toBeNull();
});

test('a payment reminder can be sent for an open invoice', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create(['email' => 'buyer@indus.test']));

    $this->actingAs($user)
        ->post(route('invoices.remind', ['current_team' => $team->slug, 'invoice' => $invoice->id]), [
            'to' => 'buyer@indus.test', 'subject' => 'Reminder', 'body' => 'Kindly pay the invoice.',
        ])
        ->assertSessionHasNoErrors();

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('buyer@indus.test'));
    expect($invoice->fresh()->reminder_count)->toBe(1)
        ->and($invoice->fresh()->last_reminder_at)->not->toBeNull();
});

test('automatic reminders follow the team\'s reminder days', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $team->update(['settings' => ['reminders' => ['enabled' => true, 'days_after_due' => [3, 10]]]]);
    $company = Company::factory()->for($team)->create(['email' => 'buyer@indus.test']);
    $invoice = issuedInvoice($this, $user, $team, $company);
    $notYet = issuedInvoice($this, $user, $team, $company);
    $invoice->forceFill(['due_date' => now()->subDays(4)])->save();
    $notYet->forceFill(['due_date' => now()->subDays(2)])->save();

    $this->artisan('invoices:send-reminders')->assertSuccessful();
    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertSent(DocumentMail::class, 1);
    expect($invoice->fresh()->reminder_count)->toBe(1)
        ->and($notYet->fresh()->reminder_count)->toBe(0);

    // A week later the invoice is 11 days overdue: the second (and last) reminder.
    $this->travel(7)->days();
    $this->artisan('invoices:send-reminders')->assertSuccessful();
    $this->travel(30)->days();
    $this->artisan('invoices:send-reminders')->assertSuccessful();

    expect($invoice->fresh()->reminder_count)->toBe(2);
});

test('no reminders are sent when the team has not switched them on', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create(['email' => 'buyer@indus.test']));
    $invoice->forceFill(['due_date' => now()->subDays(40)])->save();

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

test('an accepted quotation can be turned into a draft invoice', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $quotation = Quotation::factory()->for($team)->create(['company_id' => $company->id, 'status' => QuotationStatus::Accepted, 'title' => 'Chairs']);
    withinTeam($team, fn () => $quotation->replaceLines([
        ['type' => 'item', 'description' => 'Chair', 'quantity' => '3', 'unit_price' => '1000', 'tax_rate_id' => salesTax($team)->id],
    ]));

    $this->actingAs($user)
        ->post(route('quotations.invoice', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertRedirect();

    $invoice = Invoice::withoutGlobalScopes()->where('quotation_id', $quotation->id)->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->title)->toBe('Chairs')
        ->and($invoice->total)->toBe('3540.00')
        ->and($invoice->due_date)->not->toBeNull()
        ->and($quotation->fresh()->invoiced_at)->not->toBeNull();
});

test('turning a quotation into an invoice needs access to invoicing', function () {
    [, $team] = teamOwner();
    $quotation = Quotation::factory()->for($team)->create(['status' => QuotationStatus::Accepted, 'company_id' => Company::factory()->for($team)->create()->id]);
    $member = teamMember($team, TeamRole::Member, ['quotations' => 'write', 'invoicing' => 'read']);

    $this->actingAs($member)
        ->post(route('quotations.invoice', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertForbidden();
});

test('customers can open issued invoices through their link', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $draft = createInvoice($this, $user, $team, $company);
    $issued = issuedInvoice($this, $user, $team, $company);

    Auth::logout();

    $this->get(route('public.invoices.show', ['token' => $draft->public_token]))->assertNotFound();

    $this->get(route('public.invoices.show', ['token' => $issued->public_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Document')
            ->where('kind', 'invoice')
            ->where('document.number', 'INV-2627-0001')
            ->where('balance.balance_due', '300000.00'));

    $pdf = $this->get(route('public.invoices.pdf', ['token' => $issued->public_token]))->assertOk();
    expect($pdf->getContent())->toStartWith('%PDF');
});

test('invoices of other teams cannot be reached', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $foreign = issuedInvoice($this, $otherUser, $otherTeam, Company::factory()->for($otherTeam)->create());

    $this->actingAs($user)
        ->get(route('invoices.show', ['current_team' => $team->slug, 'invoice' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $foreign->id]), ['date' => '2026-09-30', 'amount' => '1', 'method' => 'cash'])
        ->assertNotFound();
});

test('members with read access can view but not record payments', function () {
    [$owner, $team] = teamOwner();
    $invoice = issuedInvoice($this, $owner, $team, Company::factory()->for($team)->create());
    $member = teamMember($team, TeamRole::Member, ['invoicing' => 'read']);

    $this->actingAs($member)
        ->get(route('invoices.show', ['current_team' => $team->slug, 'invoice' => $invoice->id]))
        ->assertOk();

    $this->actingAs($member)
        ->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $invoice->id]), ['date' => '2026-09-30', 'amount' => '1', 'method' => 'cash'])
        ->assertForbidden();
});

test('issued invoices can be exported for the sales tax return', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders', 'ntn' => '1234567-8']);
    issuedInvoice($this, $user, $team, $company);
    createInvoice($this, $user, $team, $company);

    $response = $this->actingAs($user)->get(route('invoices.export', ['current_team' => $team->slug]))->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('INV-2627-0001')
        ->toContain('Indus Traders')
        ->toContain('1234567-8')
        ->and(substr_count($csv, "\n"))->toBe(2);
});

test('the payments list shows the tax withheld by customers', function () {
    [$user, $team] = teamOwner();
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());
    $this->actingAs($user)->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $invoice->id]), [
        'date' => '2026-09-30', 'amount' => '280800', 'income_tax_withheld' => '10200', 'sales_tax_withheld' => '9000', 'method' => 'bank_transfer',
    ]);

    $this->actingAs($user)
        ->get(route('payments.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/Index')
            ->has('payments.data', 1)
            ->where('totals.received', '280800.00')
            ->where('totals.income_tax_withheld', '10200.00')
            ->where('totals.sales_tax_withheld', '9000.00'));
});

test('the company page lists its invoices and credit notes', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $company);
    $this->actingAs($user)->post(route('invoices.credit', ['current_team' => $team->slug, 'invoice' => $invoice->id]));

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related.invoices.total', 1)
            ->where('related.invoices.items.0.number', 'INV-2627-0001')
            ->where('related.credit_notes.total', 1));
});
