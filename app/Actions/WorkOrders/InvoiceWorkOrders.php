<?php

namespace App\Actions\WorkOrders;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\ProjectMaterial;
use App\Models\TaxRate;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns completed work orders of one customer into a draft invoice: per work order a
 * section with its hours (grouped per work type and rate) and its materials.
 */
class InvoiceWorkOrders
{
    /**
     * @param  Collection<int, WorkOrder>  $workOrders
     *
     * @throws ValidationException
     */
    public function handle(Collection $workOrders, User $user): Invoice
    {
        $this->ensureInvoiceable($workOrders);

        return DB::transaction(function () use ($workOrders, $user) {
            $ids = $workOrders->modelKeys();

            $entries = TimeEntry::query()
                ->whereIn('work_order_id', $ids)
                ->unbilled()
                ->with(['workType:id,name', 'user:id,name'])
                ->orderBy('date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $materials = ProjectMaterial::query()
                ->whereIn('work_order_id', $ids)
                ->where('is_billable', true)
                ->whereNull('invoice_id')
                ->orderBy('date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty() && $materials->isEmpty()) {
                throw ValidationException::withMessages(['work_orders' => 'There are no billable hours or materials left to invoice.']);
            }

            /** @var WorkOrder $first */
            $first = $workOrders->first();
            $invoice = $this->newInvoice($first, $workOrders, $user);
            $taxRateId = $this->defaultTaxRateId($first);
            $lines = [];

            foreach ($workOrders as $workOrder) {
                $orderEntries = $entries->where('work_order_id', $workOrder->id);
                $orderMaterials = $materials->where('work_order_id', $workOrder->id);

                if ($orderEntries->isEmpty() && $orderMaterials->isEmpty()) {
                    continue;
                }

                $lines[] = [
                    'type' => 'section',
                    'description' => sprintf('Work order %s — %s (%s)', $workOrder->number, $workOrder->title, $workOrder->date->format('d M Y')),
                ];

                foreach ($orderEntries->groupBy(fn (TimeEntry $entry) => ($entry->work_type_id ?? 0).'|'.$entry->hourly_rate) as $group) {
                    /** @var TimeEntry $entry */
                    $entry = $group->first();
                    $minutes = (int) $group->sum('duration_minutes');

                    $lines[] = [
                        'type' => 'item',
                        'description' => $entry->workType->name ?? 'Work',
                        'details' => $group->map(fn (TimeEntry $item) => $item->user?->name)->filter()->unique()->implode(', ') ?: null,
                        'quantity' => number_format($minutes / 60, 2, '.', ''),
                        'unit' => 'hour',
                        'unit_price' => $entry->hourly_rate,
                        'tax_rate_id' => $taxRateId,
                    ];
                }

                foreach ($orderMaterials as $material) {
                    $lines[] = [
                        'type' => 'item',
                        'product_id' => $material->product_id,
                        'description' => $material->description,
                        'quantity' => $material->quantity,
                        'unit' => $material->unit,
                        'unit_price' => $material->unit_price,
                        'tax_rate_id' => $material->tax_rate_id ?? $taxRateId,
                    ];
                }
            }

            $invoice->replaceLines($lines);

            TimeEntry::query()->whereKey($entries->modelKeys())->update(['invoice_id' => $invoice->id]);
            ProjectMaterial::query()->whereKey($materials->modelKeys())->update(['invoice_id' => $invoice->id]);
            WorkOrder::query()->whereKey($ids)->update(['invoice_id' => $invoice->id, 'invoiced_at' => now()]);

            foreach ($workOrders as $workOrder) {
                $workOrder->logActivity('invoiced', 'Put on a draft invoice');
            }

            return $invoice;
        });
    }

    /**
     * Only completed, not yet invoiced work orders of one customer can be invoiced together.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     *
     * @throws ValidationException
     */
    private function ensureInvoiceable(Collection $workOrders): void
    {
        if ($workOrders->isEmpty()) {
            throw ValidationException::withMessages(['work_orders' => 'Choose the work orders to invoice.']);
        }

        foreach ($workOrders as $workOrder) {
            if ($workOrder->isInvoiced()) {
                throw ValidationException::withMessages(['work_orders' => "Work order {$workOrder->number} is already invoiced."]);
            }

            if ($workOrder->isOpen()) {
                throw ValidationException::withMessages(['work_orders' => "Complete work order {$workOrder->number} before invoicing it."]);
            }

            if ($workOrder->company_id === null && $workOrder->contact_id === null) {
                throw ValidationException::withMessages(['work_orders' => "Link work order {$workOrder->number} to a customer before invoicing it."]);
            }
        }

        $customers = $workOrders->map(fn (WorkOrder $workOrder) => $workOrder->company_id !== null
            ? 'company:'.$workOrder->company_id
            : 'contact:'.$workOrder->contact_id)->unique();

        if ($customers->count() > 1) {
            throw ValidationException::withMessages(['work_orders' => 'Only work orders of the same customer can be put on one invoice.']);
        }
    }

    /**
     * Start a draft invoice for the customer of the work orders.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     */
    private function newInvoice(WorkOrder $workOrder, Collection $workOrders, User $user): Invoice
    {
        $today = CarbonImmutable::today();
        $team = $workOrder->team()->firstOrFail();
        $company = $workOrder->company;
        $term = ($company?->payment_term_id !== null ? PaymentTerm::query()->find($company->payment_term_id) : null)
            ?? PaymentTerm::query()->withoutGlobalScopes()->where('team_id', $workOrder->team_id)->where('is_default', true)->first();
        $projects = $workOrders->pluck('project_id')->unique();

        $invoice = new Invoice([
            'title' => $workOrders->count() === 1 ? $workOrder->title : 'Work orders '.$workOrders->pluck('number')->implode(', '),
            'company_id' => $workOrder->company_id,
            'contact_id' => $workOrder->contact_id,
            'project_id' => $projects->count() === 1 ? $projects->first() : null,
            'payment_term_id' => $term?->id,
            'date' => $today,
            'due_date' => $term?->dueDate($today),
            'currency' => $team->teamSettings()->currency(),
            'exchange_rate' => 1,
            'discount_percent' => 0,
            'created_by' => $user->id,
        ]);
        $invoice->team_id = $workOrder->team_id;
        $invoice->status = InvoiceStatus::Draft;
        $invoice->department_id = $workOrder->department_id ?? $team->defaultDepartment()?->id;
        $invoice->applyFurtherTax($company !== null && ! $company->sales_tax_registered);
        $invoice->refreshCustomerSnapshot();
        $invoice->save();

        return $invoice;
    }

    /**
     * The team's default tax rate for new lines.
     */
    private function defaultTaxRateId(WorkOrder $workOrder): ?int
    {
        $id = TaxRate::query()->withoutGlobalScopes()->where('team_id', $workOrder->team_id)->where('is_default', true)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
