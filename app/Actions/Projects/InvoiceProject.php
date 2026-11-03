<?php

namespace App\Actions\Projects;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\TaxRate;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the work on a project into a draft invoice: unbilled time and materials
 * (time & materials projects) or a part of the fixed price.
 */
class InvoiceProject
{
    /**
     * Invoice the billable time and materials that were not invoiced yet (up to a date, if given).
     *
     * Time is grouped per work type and hourly rate; every material gets its own line.
     *
     * @throws ValidationException
     */
    public function timeAndMaterials(Project $project, User $user, ?CarbonImmutable $until = null): Invoice
    {
        return DB::transaction(function () use ($project, $user, $until) {
            $entries = TimeEntry::query()
                ->where('project_id', $project->id)
                ->unbilled()
                ->when($until, fn ($query) => $query->whereDate('date', '<=', $until))
                ->with('workType:id,name')
                ->orderBy('date')
                ->lockForUpdate()
                ->get();

            $materials = ProjectMaterial::query()
                ->where('project_id', $project->id)
                ->where('is_billable', true)
                ->whereNull('invoice_id')
                ->when($until, fn ($query) => $query->whereDate('date', '<=', $until))
                ->orderBy('date')
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty() && $materials->isEmpty()) {
                throw ValidationException::withMessages(['project' => 'There is no unbilled time or material on this project.']);
            }

            $invoice = $this->newInvoice($project, $user);
            $taxRateId = $this->defaultTaxRateId($project);
            $lines = [];

            if ($entries->isNotEmpty()) {
                $lines[] = ['type' => 'section', 'description' => 'Time'];

                foreach ($entries->groupBy(fn (TimeEntry $entry) => ($entry->work_type_id ?? 0).'|'.$entry->hourly_rate) as $group) {
                    /** @var TimeEntry $first */
                    $first = $group->first();
                    $minutes = (int) $group->sum('duration_minutes');

                    $lines[] = [
                        'type' => 'item',
                        'description' => ($first->workType->name ?? 'Work').' — '.$project->title,
                        'details' => $this->period($group).' · '.$group->count().' '.($group->count() === 1 ? 'entry' : 'entries'),
                        'quantity' => number_format($minutes / 60, 2, '.', ''),
                        'unit' => 'hour',
                        'unit_price' => $first->hourly_rate,
                        'tax_rate_id' => $taxRateId,
                    ];
                }
            }

            if ($materials->isNotEmpty()) {
                $lines[] = ['type' => 'section', 'description' => 'Materials'];

                foreach ($materials as $material) {
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

            $project->logActivity('invoiced', sprintf(
                'Draft invoice for %s h of time and %d material line(s)',
                number_format($entries->sum('duration_minutes') / 60, 2),
                $materials->count(),
            ));

            return $invoice;
        });
    }

    /**
     * Invoice (part of) the fixed price, e.g. a 50% advance.
     *
     * @throws ValidationException
     */
    public function fixedPrice(Project $project, User $user, string $amount, ?string $description = null): Invoice
    {
        return DB::transaction(function () use ($project, $user, $amount, $description) {
            $invoice = $this->newInvoice($project, $user);

            $invoice->replaceLines([[
                'type' => 'item',
                'description' => $project->title,
                'details' => $description,
                'quantity' => '1',
                'unit' => null,
                'unit_price' => $amount,
                'tax_rate_id' => $this->defaultTaxRateId($project),
            ]]);

            $project->logActivity('invoiced', 'Draft invoice for '.$project->currency.' '.number_format((float) $amount, 2).($description ? " ({$description})" : ''));

            return $invoice;
        });
    }

    /**
     * Start a draft invoice for the project's customer.
     *
     * @throws ValidationException
     */
    private function newInvoice(Project $project, User $user): Invoice
    {
        if ($project->company_id === null && $project->contact_id === null) {
            throw ValidationException::withMessages(['project' => 'Link the project to a customer before invoicing it.']);
        }

        $today = CarbonImmutable::today();
        $company = $project->company;
        $term = ($company?->payment_term_id !== null ? PaymentTerm::query()->find($company->payment_term_id) : null)
            ?? PaymentTerm::query()->withoutGlobalScopes()->where('team_id', $project->team_id)->where('is_default', true)->first();

        $invoice = new Invoice([
            'title' => $project->title,
            'company_id' => $project->company_id,
            'contact_id' => $project->contact_id,
            'deal_id' => $project->deal_id,
            'project_id' => $project->id,
            'payment_term_id' => $term?->id,
            'date' => $today,
            'due_date' => $term?->dueDate($today),
            'currency' => $project->currency,
            'exchange_rate' => 1,
            'discount_percent' => 0,
            'created_by' => $user->id,
        ]);
        $invoice->team_id = $project->team_id;
        $invoice->status = InvoiceStatus::Draft;
        $invoice->department_id = $project->team()->firstOrFail()->defaultDepartment()?->id;
        $invoice->applyFurtherTax($company !== null && ! $company->sales_tax_registered);
        $invoice->refreshCustomerSnapshot();
        $invoice->save();

        return $invoice;
    }

    /**
     * The team's default tax rate for new lines.
     */
    private function defaultTaxRateId(Project $project): ?int
    {
        $id = TaxRate::query()->withoutGlobalScopes()->where('team_id', $project->team_id)->where('is_default', true)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Describe the dates of a group of entries, e.g. "01 Sep – 28 Sep 2026".
     *
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function period(Collection $entries): string
    {
        $first = $entries->min('date');
        $last = $entries->max('date');

        if (! $first instanceof CarbonImmutable || ! $last instanceof CarbonImmutable) {
            return '';
        }

        return $first->equalTo($last) ? $first->format('d M Y') : $first->format('d M').' – '.$last->format('d M Y');
    }
}
