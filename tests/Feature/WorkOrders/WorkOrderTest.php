<?php

use App\Enums\InvoiceStatus;
use App\Enums\TeamRole;
use App\Enums\WorkOrderStatus;
use App\Mail\DocumentMail;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\SentEmail;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * A 1×1 PNG, like a signature drawn on a canvas.
 */
function workOrderSignature(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
}

/**
 * Create a work order through the form and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function newWorkOrder($test, User $user, Team $team, array $overrides = []): WorkOrder
{
    $test->actingAs($user)
        ->post(route('work-orders.store', ['current_team' => $team->slug]), array_merge([
            'title' => 'Service of the air conditioners',
            'date' => '2026-09-30',
            'description' => 'Cleaned the filters and refilled the gas of 3 split units.',
        ], $overrides))
        ->assertSessionHasNoErrors();

    return WorkOrder::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

/**
 * Log hours on a work order through the time form.
 *
 * @param  array<string, mixed>  $overrides
 */
function workOrderHours($test, User $user, Team $team, WorkOrder $workOrder, int $minutes, array $overrides = []): void
{
    $test->actingAs($user)
        ->post(route('time.store', ['current_team' => $team->slug]), array_merge([
            'date' => '2026-09-30',
            'duration_minutes' => $minutes,
            'work_order_id' => $workOrder->id,
            'is_billable' => true,
            'hourly_rate' => '4000',
        ], $overrides))
        ->assertSessionHasNoErrors();
}

/**
 * Add material to a work order.
 *
 * @param  array<string, mixed>  $overrides
 */
function workOrderMaterial($test, User $user, Team $team, WorkOrder $workOrder, array $overrides = []): void
{
    $test->actingAs($user)
        ->post(route('work-orders.materials.store', ['current_team' => $team->slug, 'work_order' => $workOrder->id]), array_merge([
            'date' => '2026-09-30',
            'description' => 'Refrigerant R410A',
            'quantity' => 2,
            'unit' => 'kg',
            'unit_price' => '1500',
            'is_billable' => true,
        ], $overrides))
        ->assertSessionHasNoErrors();
}

/**
 * The route parameters of a work order.
 *
 * @return array<string, mixed>
 */
function workOrderRoute(Team $team, WorkOrder $workOrder): array
{
    return ['current_team' => $team->slug, 'work_order' => $workOrder->id];
}

test('a work order gets a number and the visiting address of the customer', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $company->syncAddresses([
        ['type' => 'primary', 'line_1' => '5 Shahrah-e-Faisal', 'city' => 'Karachi'],
        ['type' => 'visiting', 'line_1' => '12 Mall Road', 'city' => 'Lahore'],
    ]);

    $workOrder = newWorkOrder($this, $user, $team, ['company_id' => $company->id, 'assignee_id' => $user->id]);

    expect($workOrder->number)->toBe('WO-2627-0001')
        ->and($workOrder->status)->toBe(WorkOrderStatus::Open)
        ->and($workOrder->location)->toContain('12 Mall Road')
        ->and($workOrder->department_id)->not->toBeNull()
        ->and($workOrder->public_token)->toHaveLength(48);

    $this->get(route('work-orders.show', workOrderRoute($team, $workOrder)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-orders/Show')
            ->where('workOrder.number', 'WO-2627-0001')
            ->where('workOrder.status_label', 'Open')
            ->where('workOrder.customer_label', 'Indus Traders')
            ->where('workOrder.assignee.id', $user->id)
            ->where('emailDraft.subject', fn (string $subject) => str_contains($subject, 'WO-2627-0001')));
});

test('hours and materials are booked on the work order, its customer and project', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $project = Project::factory()->for($team)->create(['company_id' => $company->id, 'hourly_rate' => '5000']);
    $workOrder = newWorkOrder($this, $user, $team, ['company_id' => $company->id, 'project_id' => $project->id]);

    workOrderHours($this, $user, $team, $workOrder, 90, ['hourly_rate' => null]);
    workOrderMaterial($this, $user, $team, $workOrder);

    $entry = TimeEntry::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->sole();
    $material = ProjectMaterial::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->sole();

    expect($entry->project_id)->toBe($project->id)
        ->and($entry->company_id)->toBe($company->id)
        ->and($entry->hourly_rate)->toBe('5000.00')
        ->and($material->project_id)->toBe($project->id);

    $this->get(route('work-orders.show', workOrderRoute($team, $workOrder)))
        ->assertInertia(fn (Assert $page) => $page
            ->has('timeEntries', 1)
            ->has('materials', 1)
            ->where('totals.minutes', 90)
            ->where('totals.time_value', '7500.00')
            ->where('totals.materials_value', '3000.00')
            ->where('totals.total_value', '10500.00'));
});

test('a completed work order no longer changes until it is reopened', function () {
    [$user, $team] = teamOwner();
    $workOrder = newWorkOrder($this, $user, $team);
    workOrderHours($this, $user, $team, $workOrder, 60);
    $entry = TimeEntry::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->sole();

    $this->post(route('work-orders.complete', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::Completed)
        ->and($workOrder->completed_at)->not->toBeNull();

    $this->post(route('time.store', ['current_team' => $team->slug]), ['date' => '2026-09-30', 'duration_minutes' => 30, 'work_order_id' => $workOrder->id])
        ->assertSessionHasErrors('entry');
    $this->delete(route('time.destroy', ['current_team' => $team->slug, 'time_entry' => $entry->id]))
        ->assertSessionHasErrors('entry');
    $this->post(route('work-orders.materials.store', workOrderRoute($team, $workOrder)), ['date' => '2026-09-30', 'description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100])
        ->assertSessionHasErrors('material');
    $this->put(route('work-orders.update', workOrderRoute($team, $workOrder)), ['title' => 'Changed', 'date' => '2026-09-30'])
        ->assertSessionHasErrors('work_order');

    $this->post(route('work-orders.reopen', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();

    expect($workOrder->fresh()->status)->toBe(WorkOrderStatus::Open);

    $this->delete(route('time.destroy', ['current_team' => $team->slug, 'time_entry' => $entry->id]))
        ->assertSessionHasNoErrors();

    expect(TimeEntry::withoutGlobalScopes()->whereKey($entry->id)->exists())->toBeFalse();
});

test('an empty work order cannot be completed', function () {
    [$user, $team] = teamOwner();
    $workOrder = newWorkOrder($this, $user, $team, ['description' => null]);

    $this->post(route('work-orders.complete', workOrderRoute($team, $workOrder)))
        ->assertSessionHasErrors('work_order');
});

test('the customer signs on the spot, which completes the work order', function () {
    [$user, $team] = teamOwner();
    $member = teamMember($team);
    $workOrder = newWorkOrder($this, $user, $team);

    $this->actingAs($member)
        ->post(route('work-orders.sign', workOrderRoute($team, $workOrder)), [
            'name' => 'Ali Raza',
            'signature' => workOrderSignature(),
            'remarks' => 'Quick and clean work.',
        ])
        ->assertSessionHasNoErrors();

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::Completed)
        ->and($workOrder->isSigned())->toBeTrue()
        ->and($workOrder->signed_by_name)->toBe('Ali Raza')
        ->and($workOrder->customer_remarks)->toBe('Quick and clean work.')
        ->and($workOrder->displayStatus()['key'])->toBe('signed');

    expect(ActivityLog::withoutGlobalScopes()->where('subject_type', 'work_order')->where('subject_id', $workOrder->id)->where('event', 'signed')->value('description'))
        ->toBe('Signed by Ali Raza');

    // A work order is signed once.
    $this->actingAs($member)
        ->post(route('work-orders.sign', workOrderRoute($team, $workOrder)), ['name' => 'Someone else', 'signature' => workOrderSignature()])
        ->assertSessionHasErrors('work_order');

    // Removing the customer's signature is for owners and admins.
    $this->actingAs($member)->post(route('work-orders.reopen', workOrderRoute($team, $workOrder)))->assertForbidden();
    $this->actingAs($user)->post(route('work-orders.reopen', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();

    $workOrder->refresh();

    expect($workOrder->isOpen())->toBeTrue()
        ->and($workOrder->signature)->toBeNull()
        ->and($workOrder->signed_at)->toBeNull();
});

test('the customer opens the work order through the link and signs it online', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $workOrder = newWorkOrder($this, $user, $team, ['company_id' => $company->id, 'internal_notes' => 'SECRET-NOTE: pays late']);
    workOrderMaterial($this, $user, $team, $workOrder);
    $this->post(route('work-orders.complete', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();

    Auth::logout();

    $this->get(route('public.work-orders.show', ['token' => $workOrder->public_token]))
        ->assertOk()
        ->assertDontSee('SECRET-NOTE')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/WorkOrder')
            ->where('workOrder.title', 'Service of the air conditioners')
            ->missing('workOrder.internal_notes')
            ->where('materials.0.unit_price', null)
            ->where('canSign', true));

    $this->post(route('public.work-orders.sign', ['token' => $workOrder->public_token]), ['name' => 'Sara Khan', 'signature' => workOrderSignature()])
        ->assertSessionHasErrors('agree');

    $this->post(route('public.work-orders.sign', ['token' => $workOrder->public_token]), ['name' => 'Sara Khan', 'signature' => workOrderSignature(), 'agree' => true])
        ->assertRedirect();

    $workOrder->refresh();

    expect($workOrder->isSigned())->toBeTrue()
        ->and($workOrder->signed_by_name)->toBe('Sara Khan')
        ->and($workOrder->signed_ip)->not->toBeNull();

    $pdf = $this->get(route('public.work-orders.pdf', ['token' => $workOrder->public_token]));
    $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect(ActivityLog::withoutGlobalScopes()->where('subject_type', 'work_order')->where('subject_id', $workOrder->id)->pluck('event')->all())
        ->toContain('viewed', 'signed');
});

test('the work order can be downloaded as PDF', function () {
    [$user, $team] = teamOwner();
    $workOrder = newWorkOrder($this, $user, $team, ['show_prices' => true]);
    workOrderHours($this, $user, $team, $workOrder, 45);
    workOrderMaterial($this, $user, $team, $workOrder);

    $response = $this->get(route('work-orders.pdf', [...workOrderRoute($team, $workOrder), 'download' => 1]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="work-order-wo-2627-0001.pdf"')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

test('the work order is e-mailed to the customer with its PDF', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['email' => 'accounts@indus.test']);
    $workOrder = newWorkOrder($this, $user, $team, ['company_id' => $company->id]);

    $this->get(route('work-orders.show', workOrderRoute($team, $workOrder)))
        ->assertInertia(fn (Assert $page) => $page->where('emailDraft.to', 'accounts@indus.test'));

    $this->post(route('work-orders.send', workOrderRoute($team, $workOrder)), [
        'to' => 'accounts@indus.test',
        'subject' => 'Work order WO-2627-0001',
        'body' => 'Please find the work order attached.',
        'attach_pdf' => true,
    ])->assertSessionHasNoErrors();

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('accounts@indus.test')
        && $mail->pdfName === 'work-order-wo-2627-0001.pdf'
        && str_starts_with((string) $mail->pdf, '%PDF'));

    $sent = SentEmail::withoutGlobalScopes()->where('emailable_type', 'work_order')->where('emailable_id', $workOrder->id)->sole();

    expect($sent->status)->toBe('sent')
        ->and($sent->attachments)->toBe(['work-order-wo-2627-0001.pdf']);
});

test('completed work orders of one customer are put on one draft invoice', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['sales_tax_registered' => true]);
    $installation = WorkType::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Installation')->firstOrFail();

    $first = newWorkOrder($this, $user, $team, ['company_id' => $company->id, 'title' => 'Installation of 2 split units']);
    workOrderHours($this, $user, $team, $first, 90, ['work_type_id' => $installation->id]);
    workOrderHours($this, $user, $team, $first, 30, ['work_type_id' => $installation->id]);
    workOrderMaterial($this, $user, $team, $first);
    workOrderMaterial($this, $user, $team, $first, ['description' => 'Remote control (warranty)', 'is_billable' => false]);

    $second = newWorkOrder($this, $user, $team, ['company_id' => $company->id, 'title' => 'Yearly service']);
    workOrderHours($this, $user, $team, $second, 60);

    foreach ([$first, $second] as $workOrder) {
        $this->post(route('work-orders.complete', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();
    }

    $response = $this->post(route('work-orders.invoice', ['current_team' => $team->slug]), ['work_orders' => [$first->id, $second->id]]);

    $invoice = Invoice::withoutGlobalScopes()->where('team_id', $team->id)->sole();
    $response->assertRedirect(route('invoices.edit', ['current_team' => $team->slug, 'invoice' => $invoice->id]));

    $lines = $invoice->lines()->get();

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->company_id)->toBe($company->id)
        ->and($invoice->title)->toBe('Work orders WO-2627-0001, WO-2627-0002')
        ->and($lines->pluck('description')->all())->toBe([
            'Work order WO-2627-0001 — Installation of 2 split units (30 Sep 2026)',
            'Installation',
            'Refrigerant R410A',
            'Work order WO-2627-0002 — Yearly service (30 Sep 2026)',
            'Work',
        ])
        ->and((float) $lines[1]->quantity)->toBe(2.0)
        ->and((float) $lines[1]->unit_price)->toBe(4000.0)
        ->and((float) $invoice->subtotal)->toBe(15000.0)
        ->and((float) $invoice->total)->toBe(17700.0);

    expect(TimeEntry::withoutGlobalScopes()->whereIn('work_order_id', [$first->id, $second->id])->whereNull('invoice_id')->count())->toBe(0)
        ->and(ProjectMaterial::withoutGlobalScopes()->where('work_order_id', $first->id)->whereNotNull('invoice_id')->count())->toBe(1)
        ->and($first->fresh()->invoice_id)->toBe($invoice->id)
        ->and($second->fresh()->displayStatus()['key'])->toBe('invoiced');

    // Invoiced work orders stay as they are.
    $this->delete(route('work-orders.destroy', workOrderRoute($team, $first)))->assertSessionHasErrors('work_order');
    $this->post(route('work-orders.reopen', workOrderRoute($team, $first)))->assertSessionHasErrors('work_order');
});

test('only completed work orders of one customer can be invoiced together', function () {
    [$user, $team] = teamOwner();
    $indus = Company::factory()->for($team)->create();
    $other = Company::factory()->for($team)->create();

    $open = newWorkOrder($this, $user, $team, ['company_id' => $indus->id]);
    workOrderHours($this, $user, $team, $open, 60);

    $this->post(route('work-orders.invoice', ['current_team' => $team->slug]), ['work_orders' => [$open->id]])
        ->assertSessionHasErrors('work_orders');

    $done = newWorkOrder($this, $user, $team, ['company_id' => $other->id]);
    workOrderHours($this, $user, $team, $done, 60);

    foreach ([$open, $done] as $workOrder) {
        $this->post(route('work-orders.complete', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();
    }

    $this->post(route('work-orders.invoice', ['current_team' => $team->slug]), ['work_orders' => [$open->id, $done->id]])
        ->assertSessionHasErrors('work_orders');

    expect(Invoice::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(0);
});

test('deleting the draft invoice makes the work order invoiceable again', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $workOrder = newWorkOrder($this, $user, $team, ['company_id' => $company->id]);
    workOrderHours($this, $user, $team, $workOrder, 60);
    $this->post(route('work-orders.complete', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();
    $this->post(route('work-orders.invoice', ['current_team' => $team->slug]), ['work_orders' => [$workOrder->id]])->assertSessionHasNoErrors();

    $invoice = Invoice::withoutGlobalScopes()->where('team_id', $team->id)->sole();

    $this->delete(route('invoices.destroy', ['current_team' => $team->slug, 'invoice' => $invoice->id]))->assertSessionHasNoErrors();

    expect($workOrder->fresh()->invoice_id)->toBeNull()
        ->and($workOrder->fresh()->invoiced_at)->toBeNull()
        ->and(TimeEntry::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->value('invoice_id'))->toBeNull();
});

test('hours of a project work order are invoiced once, with the project or the work order', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $project = Project::factory()->for($team)->create(['company_id' => $company->id]);
    $workOrder = newWorkOrder($this, $user, $team, ['company_id' => $company->id, 'project_id' => $project->id]);
    workOrderHours($this, $user, $team, $workOrder, 60, ['hourly_rate' => null]);
    $this->post(route('work-orders.complete', workOrderRoute($team, $workOrder)))->assertSessionHasNoErrors();

    $this->post(route('projects.invoice', ['current_team' => $team->slug, 'project' => $project->id]), ['mode' => 'time_materials'])
        ->assertSessionHasNoErrors();

    $this->post(route('work-orders.invoice', ['current_team' => $team->slug]), ['work_orders' => [$workOrder->id]])
        ->assertSessionHasErrors('work_orders');

    expect(Invoice::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(1);
});

test('deleting a work order removes its hours and materials', function () {
    [$user, $team] = teamOwner();
    $workOrder = newWorkOrder($this, $user, $team);
    workOrderHours($this, $user, $team, $workOrder, 60);
    workOrderMaterial($this, $user, $team, $workOrder);

    $this->delete(route('work-orders.destroy', workOrderRoute($team, $workOrder)))
        ->assertRedirect(route('work-orders.index', ['current_team' => $team->slug]));

    expect(WorkOrder::withoutGlobalScopes()->find($workOrder->id)?->trashed())->toBeTrue()
        ->and(TimeEntry::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->count())->toBe(0)
        ->and(ProjectMaterial::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->count())->toBe(0);
});

test('the list shows the work orders with their status and hours', function () {
    [$user, $team] = teamOwner();
    newWorkOrder($this, $user, $team, ['title' => 'Open job']);
    $done = newWorkOrder($this, $user, $team, ['title' => 'Finished job']);
    workOrderHours($this, $user, $team, $done, 75);
    $this->post(route('work-orders.complete', workOrderRoute($team, $done)))->assertSessionHasNoErrors();

    $this->get(route('work-orders.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-orders/Index')
            ->has('workOrders.data', 2)
            ->where('summary.open', 1)
            ->where('summary.unsigned', 1)
            ->where('summary.to_invoice', 1));

    $this->get(route('work-orders.index', ['current_team' => $team->slug, 'status' => 'to_invoice']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('workOrders.data', 1)
            ->where('workOrders.data.0.title', 'Finished job')
            ->where('workOrders.data.0.logged_minutes', 75));

    $this->get(route('work-orders.index', ['current_team' => $team->slug, 'search' => 'Open']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('workOrders.data', 1)
            ->where('workOrders.data.0.title', 'Open job'));
});

test('a work order is started from a ticket and listed on the customer page', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $ticket = Ticket::factory()->for($team)->create(['subject' => 'AC leaks water', 'company_id' => $company->id]);

    $this->actingAs($user)
        ->get(route('work-orders.create', ['current_team' => $team->slug, 'ticket' => $ticket->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-orders/Create')
            ->where('prefill.title', 'AC leaks water')
            ->where('prefill.company.name', 'Indus Traders')
            ->where('prefill.ticket.id', $ticket->id));

    $workOrder = newWorkOrder($this, $user, $team, ['company_id' => $company->id, 'ticket_id' => $ticket->id]);

    expect($workOrder->ticket_id)->toBe($ticket->id);

    $this->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related.work_orders.total', 1)
            ->where('related.work_orders.items.0.number', 'WO-2627-0001'));
});

test('work orders are private to the team and the work orders module', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $foreign = newWorkOrder($this, $otherUser, $otherTeam);
    $member = teamMember($team, TeamRole::Member, ['work_orders' => 'none']);

    $this->actingAs($user)
        ->get(route('work-orders.show', workOrderRoute($team, $foreign)))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('time.store', ['current_team' => $team->slug]), ['date' => '2026-09-30', 'duration_minutes' => 30, 'work_order_id' => $foreign->id])
        ->assertSessionHasErrors('work_order_id');

    $this->actingAs($member)
        ->get(route('work-orders.index', ['current_team' => $team->slug]))
        ->assertForbidden();

    Auth::logout();

    $this->get(route('public.work-orders.show', ['token' => 'not-a-real-token']))->assertNotFound();
});
