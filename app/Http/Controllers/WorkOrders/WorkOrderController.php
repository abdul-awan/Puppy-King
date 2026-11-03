<?php

namespace App\Http\Controllers\WorkOrders;

use App\Actions\WorkOrders\InvoiceWorkOrders;
use App\Actions\WorkOrders\SignWorkOrder;
use App\Enums\AddressType;
use App\Enums\DocumentType;
use App\Enums\EmailTemplateType;
use App\Enums\TeamRole;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\SendDocumentRequest;
use App\Http\Requests\WorkOrders\WorkOrderRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\ProjectMaterialResource;
use App\Http\Resources\TimeEntryResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Rules\PngDataUri;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use App\Support\DocumentNumberer;
use App\Support\Documents\DocumentMailer;
use App\Support\Documents\WorkOrderPdf;
use App\Support\Notifier;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use App\Support\Timeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WorkOrderController extends Controller
{
    /**
     * Display the work orders, newest first.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $assignee = $request->string('assignee')->toString();

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($status, ['open', 'unsigned', 'to_invoice', 'invoiced'], true) ? $status : 'all',
            'assignee' => $assignee === 'me' || ctype_digit($assignee) ? $assignee : 'all',
            'company' => $request->integer('company') ?: null,
            ...DateRange::filters($request),
        ];

        $query = WorkOrder::query()
            ->with(['company:id,name', 'contact:id,first_name,last_name', 'assignee:id,name', 'invoice:id,number'])
            ->withSum('timeEntries as logged_minutes', 'duration_minutes')
            ->search($filters['search'])
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->where('status', WorkOrderStatus::Open))
            ->when($filters['status'] === 'unsigned', fn (Builder $query) => $query->where('status', WorkOrderStatus::Completed)->whereNull('signed_at')->whereNull('invoice_id'))
            ->when($filters['status'] === 'to_invoice', fn (Builder $query) => $query->where('status', WorkOrderStatus::Completed)->whereNull('invoice_id'))
            ->when($filters['status'] === 'invoiced', fn (Builder $query) => $query->whereNotNull('invoice_id'))
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $request->user()->id))
            ->when(ctype_digit($filters['assignee']), fn (Builder $query) => $query->where('assignee_id', (int) $filters['assignee']))
            ->when($filters['company'], fn (Builder $query, int $company) => $query->where('company_id', $company));

        DateRange::apply($query, 'date', DateRange::resolve($filters));

        return Inertia::render('work-orders/Index', [
            'workOrders' => WorkOrderResource::collection($query->latest('date')->latest('id')->paginate(25)->withQueryString()),
            'filters' => $filters,
            'users' => TeamOptions::users(),
            'periods' => DateRange::options(),
            'summary' => [
                'open' => WorkOrder::query()->where('status', WorkOrderStatus::Open)->count(),
                'unsigned' => WorkOrder::query()->where('status', WorkOrderStatus::Completed)->whereNull('signed_at')->whereNull('invoice_id')->count(),
                'to_invoice' => WorkOrder::query()->where('status', WorkOrderStatus::Completed)->whereNull('invoice_id')->count(),
            ],
        ]);
    }

    /**
     * Show the form for a new work order (from scratch, a ticket, a project or a customer).
     */
    public function create(Request $request): Response
    {
        $ticket = $request->integer('ticket') ? Ticket::query()->find($request->integer('ticket')) : null;
        $project = $request->integer('project') ? Project::query()->find($request->integer('project')) : $ticket?->project;
        $contact = $request->integer('contact')
            ? Contact::query()->find($request->integer('contact'))
            : ($ticket->contact ?? $project->contact ?? null);
        $company = $request->integer('company')
            ? Company::query()->find($request->integer('company'))
            : ($ticket->company ?? $project->company ?? $contact?->companies()->first());

        return Inertia::render('work-orders/Create', [
            'prefill' => [
                'title' => $ticket->subject ?? '',
                'company' => $company?->only(['id', 'name']),
                'contact' => $contact ? ['id' => $contact->id, 'name' => $contact->full_name] : null,
                'project' => $project ? ['id' => $project->id, 'name' => trim(($project->number ?? '').' '.$project->title)] : null,
                'ticket' => $ticket ? ['id' => $ticket->id, 'name' => trim(($ticket->number ?? '').' '.$ticket->subject)] : null,
                'location' => $this->siteAddress($company, $contact),
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a new work order.
     */
    public function store(WorkOrderRequest $request, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $workOrder = DB::transaction(function () use ($request, $numberer, $team) {
            $workOrder = new WorkOrder([...$request->validated(), 'created_by' => $request->user()->id]);
            $workOrder->team_id = $team->id;
            $workOrder->department_id ??= $team->defaultDepartment()?->id;
            $workOrder->location ??= $this->siteAddress($workOrder->company, $workOrder->contact);
            $workOrder->number = $numberer->next($team, DocumentType::WorkOrder, $workOrder->date);
            $workOrder->save();

            return $workOrder;
        });

        Notifier::workOrderAssigned($workOrder, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work order :number created.', ['number' => $workOrder->number])]);

        return to_route('work-orders.show', $workOrder);
    }

    /**
     * Display the work order with its hours, materials and signature.
     */
    public function show(Request $request, WorkOrder $workOrder, DocumentMailer $mailer): Response
    {
        $workOrder->load([
            'company:id,name,email', 'contact', 'project:id,number,title', 'ticket:id,number,subject', 'assignee:id,name',
            'department', 'invoice:id,number', 'creator:id,name',
            'timeEntries' => fn ($query) => $query->with(['user:id,name', 'workType:id,name', 'invoice:id,number'])->orderBy('date')->orderBy('id'),
            'materials' => fn ($query) => $query->with('invoice:id,number')->orderBy('date')->orderBy('id'),
        ]);

        $team = app(CurrentTeam::class)->team();

        return Inertia::render('work-orders/Show', [
            'workOrder' => WorkOrderResource::make($workOrder),
            'signature' => $workOrder->signature,
            'timeEntries' => TimeEntryResource::collection($workOrder->timeEntries),
            'materials' => ProjectMaterialResource::collection($workOrder->materials),
            'totals' => $workOrder->totals(),
            'currency' => $team->teamSettings()->currency(),
            'attachments' => AttachmentResource::collection($workOrder->attachments()->with('user:id,name')->get()),
            'timeline' => Timeline::for($workOrder),
            'workTypes' => TeamOptions::workTypes(),
            'taxRates' => TeamOptions::taxRates(),
            // Owners and admins may log hours for the technicians.
            'users' => $this->isManager($request->user()) ? TeamOptions::users() : [],
            'isManager' => $this->isManager($request->user()),
            'emailDraft' => $mailer->compose(
                $workOrder->team_id,
                EmailTemplateType::WorkOrder,
                $this->placeholders($workOrder, $request->user()),
                (string) $workOrder->customerEmail(),
                'Work order '.$workOrder->number,
            ),
        ]);
    }

    /**
     * Show the form for editing the (open) work order.
     */
    public function edit(WorkOrder $workOrder): Response
    {
        $this->ensureOpen($workOrder);

        $workOrder->load(['company:id,name', 'contact', 'project:id,number,title', 'ticket:id,number,subject']);

        return Inertia::render('work-orders/Edit', [
            'workOrder' => WorkOrderResource::make($workOrder),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the (open) work order.
     */
    public function update(WorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->ensureOpen($workOrder);

        DB::transaction(function () use ($request, $workOrder) {
            $workOrder->update($request->validated());

            // The hours and materials follow the customer and project of the work order.
            if ($workOrder->wasChanged(['company_id', 'project_id'])) {
                $workOrder->timeEntries()->whereNull('invoice_id')->update([
                    'company_id' => $workOrder->company_id ?? $workOrder->project()->value('company_id'),
                    'project_id' => $workOrder->project_id,
                ]);
                $workOrder->materials()->whereNull('invoice_id')->update(['project_id' => $workOrder->project_id]);
            }
        });

        if ($workOrder->wasChanged('assignee_id')) {
            Notifier::workOrderAssigned($workOrder, $request->user());
        }

        return to_route('work-orders.show', $workOrder);
    }

    /**
     * Delete the work order with its hours and materials (not once any of it is invoiced).
     */
    public function destroy(WorkOrder $workOrder): RedirectResponse
    {
        if ($workOrder->isInvoiced()
            || $workOrder->timeEntries()->whereNotNull('invoice_id')->exists()
            || $workOrder->materials()->whereNotNull('invoice_id')->exists()) {
            throw ValidationException::withMessages(['work_order' => 'This work order (or part of it) is on an invoice and cannot be deleted.']);
        }

        DB::transaction(function () use ($workOrder) {
            $workOrder->timeEntries()->delete();
            $workOrder->materials()->delete();
            $workOrder->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work order deleted.')]);

        return to_route('work-orders.index');
    }

    /**
     * Mark the work as done; the hours and materials no longer change.
     */
    public function complete(WorkOrder $workOrder): RedirectResponse
    {
        $this->ensureOpen($workOrder);

        if (blank($workOrder->description) && ! $workOrder->timeEntries()->exists() && ! $workOrder->materials()->exists()) {
            throw ValidationException::withMessages(['work_order' => 'Describe the work or add hours or materials before completing the work order.']);
        }

        $workOrder->forceFill(['status' => WorkOrderStatus::Completed, 'completed_at' => now()])->saveQuietly();
        $workOrder->logActivity('completed', 'Completed');

        return back();
    }

    /**
     * Open a completed work order again (removing the signature: the customer signed the old version).
     */
    public function reopen(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        if ($workOrder->isInvoiced()) {
            throw ValidationException::withMessages(['work_order' => 'This work order is invoiced and cannot be reopened.']);
        }

        if ($workOrder->isOpen()) {
            return back();
        }

        $signed = $workOrder->isSigned();

        abort_if($signed && ! $this->isManager($request->user()), 403, 'Only owners and admins can reopen a signed work order.');

        $workOrder->forceFill([
            'status' => WorkOrderStatus::Open,
            'completed_at' => null,
            'signed_by_name' => null,
            'signature' => null,
            'signed_at' => null,
            'signed_ip' => null,
            'customer_remarks' => null,
        ])->saveQuietly();

        $workOrder->logActivity('reopened', $signed ? 'Reopened; the signature of the customer was removed' : 'Reopened');

        return back();
    }

    /**
     * The customer signs on the spot (on the technician's phone or tablet).
     */
    public function sign(Request $request, WorkOrder $workOrder, SignWorkOrder $signer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'signature' => ['required', 'string', 'max:700000', new PngDataUri],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], [
            'signature.required' => 'Ask the customer to sign in the box.',
        ]);

        $signer->sign($workOrder, $validated['name'], $validated['signature'], $validated['remarks'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signed by :name.', ['name' => $validated['name']])]);

        return back();
    }

    /**
     * E-mail the work order (with its PDF) to the customer.
     */
    public function send(SendDocumentRequest $request, WorkOrder $workOrder, DocumentMailer $mailer, WorkOrderPdf $pdf): RedirectResponse
    {
        $message = $request->message();

        $sent = $mailer->deliver(
            $workOrder,
            $workOrder->department ?? app(CurrentTeam::class)->team()->defaultDepartment(),
            $request->user(),
            $message,
            $message['attach_pdf'] ? $pdf->render($workOrder) : null,
            $pdf->filename($workOrder),
        );

        if ($sent->status !== 'sent') {
            throw ValidationException::withMessages(['to' => 'The e-mail could not be sent: '.$sent->error]);
        }

        $workOrder->logActivity('sent', "Sent to {$sent->to}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work order sent to :to.', ['to' => $sent->to])]);

        return back();
    }

    /**
     * Show or download the work order as PDF.
     */
    public function pdf(Request $request, WorkOrder $workOrder, WorkOrderPdf $pdf): HttpResponse
    {
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->render($workOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($workOrder).'"',
        ]);
    }

    /**
     * Put one or more completed work orders of a customer on a draft invoice.
     */
    public function invoice(Request $request, InvoiceWorkOrders $invoicer): RedirectResponse
    {
        $validated = $request->validate([
            'work_orders' => ['required', 'array', 'min:1', 'max:50'],
            'work_orders.*' => ['integer', 'distinct', TeamRule::exists('work_orders', softDeletes: true)],
        ], [
            'work_orders.required' => 'Choose the work orders to invoice.',
        ]);

        $workOrders = WorkOrder::query()
            ->with('company')
            ->whereKey($validated['work_orders'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $invoice = $invoicer->handle($workOrders, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft invoice created. Check it and issue it when ready.')]);

        return to_route('invoices.edit', $invoice);
    }

    /**
     * Completed work orders no longer change.
     */
    protected function ensureOpen(WorkOrder $workOrder): void
    {
        if (! $workOrder->isOpen()) {
            throw ValidationException::withMessages(['work_order' => 'This work order is completed. Reopen it to make changes.']);
        }
    }

    /**
     * The address where the work is done: the visiting address of the customer, if known.
     */
    protected function siteAddress(?Company $company, ?Contact $contact): ?string
    {
        $address = ($company ?? $contact)?->addressFor(AddressType::Visiting);

        return $address ? implode("\n", $address->lines()) : null;
    }

    /**
     * Placeholder values for the e-mail template.
     *
     * @return array<string, string|int|float|null>
     */
    protected function placeholders(WorkOrder $workOrder, User $user): array
    {
        $team = app(CurrentTeam::class)->team();
        $department = $workOrder->department ?? $team->defaultDepartment();

        return [
            '{customer_name}' => $workOrder->customerLabel(),
            '{contact_name}' => $workOrder->contact->full_name ?? $workOrder->customerLabel(),
            '{company_name}' => $department?->displayName() ?? $team->name,
            '{user_name}' => $user->name,
            '{work_order_number}' => $workOrder->number,
            '{work_order_title}' => $workOrder->title,
            '{link}' => $workOrder->publicUrl(),
        ];
    }

    /**
     * Owners and admins may log hours for others and reopen signed work orders.
     */
    protected function isManager(User $user): bool
    {
        return $user->teamRole(app(CurrentTeam::class)->team())?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Get the option lists for the work order form.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'users' => TeamOptions::users(),
            'departments' => TeamOptions::departments(),
        ];
    }
}
