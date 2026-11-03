<?php

namespace App\Support\Documents;

use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Renders work orders as A4 PDF files.
 */
class WorkOrderPdf
{
    /**
     * Render the work order and return the PDF contents.
     */
    public function render(WorkOrder $workOrder): string
    {
        return Pdf::loadView('pdf.work-order', $this->data($workOrder))
            ->setPaper('a4')
            // Embed only the characters used instead of whole font files.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * Get the file name of the PDF.
     */
    public function filename(WorkOrder $workOrder): string
    {
        return Str::slug('Work order '.($workOrder->number ?? $workOrder->id)).'.pdf';
    }

    /**
     * Get the data used by the PDF view (and the customer's page).
     *
     * @return array<string, mixed>
     */
    public function data(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing([
            'department', 'company', 'contact', 'assignee:id,name', 'project:id,number,title',
            'timeEntries' => fn ($query) => $query->with(['user:id,name', 'workType:id,name'])->orderBy('date')->orderBy('id'),
            'materials' => fn ($query) => $query->orderBy('date')->orderBy('id'),
        ]);

        $team = $workOrder->team()->firstOrFail();
        $department = $workOrder->department ?? $team->defaultDepartment();
        $contact = $workOrder->contact;

        return [
            'workOrder' => $workOrder,
            'department' => $department,
            'logo' => $department?->logoDataUri(),
            'meta' => array_filter([
                'Work order no.' => $workOrder->number,
                'Date' => $workOrder->date->format('d M Y'),
                'Technician' => $workOrder->assignee?->name,
                'Project' => $workOrder->project ? trim(($workOrder->project->number ?? '').' '.$workOrder->project->title) : null,
            ], fn ($value) => filled($value)),
            'customer' => [
                'name' => $workOrder->customerLabel(),
                'contact' => $workOrder->company !== null ? $contact?->full_name : null,
                'phone' => $contact->mobile ?? $contact->phone ?? $workOrder->company?->phone,
                'email' => $workOrder->customerEmail(),
            ],
            'entries' => $workOrder->timeEntries,
            'materials' => $workOrder->materials,
            'totals' => $workOrder->totals(),
            'currency' => $team->teamSettings()->currency(),
            'showPrices' => $workOrder->show_prices,
        ];
    }
}
