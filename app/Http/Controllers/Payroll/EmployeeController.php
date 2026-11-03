<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\DocumentType;
use App\Enums\EmploymentType;
use App\Enums\PaymentMethod;
use App\Enums\PayrollRunStatus;
use App\Enums\SalaryTaxMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\EmployeeRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePayItem;
use App\Models\PayComponent;
use App\Models\Payslip;
use App\Support\CurrentTeam;
use App\Support\DocumentNumberer;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\Payroll\PayrollSettings;
use App\Support\Payroll\SalaryEstimate;
use App\Support\Payroll\TaxYear;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The people on the payroll, with their salary, bank account and tax details.
 */
class EmployeeController extends Controller
{
    /**
     * The status filters of the list (current = not left yet).
     */
    private const STATUS_FILTERS = ['current', 'left', 'all'];

    /**
     * Display a listing of the employees.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $settings = PayrollSettings::for(app(CurrentTeam::class)->team());

        $employees = $this->filteredQuery($filters)
            ->with('payItems.component')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $estimates = [];

        foreach ($employees->items() as $employee) {
            $estimates[$employee->id] = SalaryEstimate::for($employee, $settings);
        }

        $current = Employee::query()->with('payItems.component')->current()->get();
        $gross = BigDecimal::zero();
        $cost = BigDecimal::zero();

        foreach ($current as $employee) {
            $estimate = SalaryEstimate::for($employee, $settings);
            $gross = $gross->plus($estimate['gross']);
            $cost = $cost->plus($estimate['cost']);
        }

        return Inertia::render('payroll/employees/Index', [
            'employees' => EmployeeResource::collection($employees),
            'estimates' => (object) $estimates,
            'filters' => $filters,
            'departments' => $this->distinct('department'),
            'summary' => [
                'current' => $current->count(),
                'monthly_gross' => (string) $gross->toScale(2),
                'monthly_cost' => (string) $cost->toScale(2),
                'left' => Employee::query()->whereNotNull('leaving_date')->whereDate('leaving_date', '<', CarbonImmutable::today())->count(),
            ],
        ]);
    }

    /**
     * Show the form for adding an employee.
     */
    public function create(DocumentNumberer $numberer): Response
    {
        $team = app(CurrentTeam::class)->team();

        return Inertia::render('payroll/employees/Create', [
            'prefill' => [
                'joining_date' => CarbonImmutable::today()->toDateString(),
                'eobi' => PayrollSettings::for($team)->eobiDefault(),
                'next_code' => $numberer->preview($team, DocumentType::Employee, now()),
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a new employee with their salary.
     */
    public function store(EmployeeRequest $request, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $employee = DB::transaction(function () use ($request, $numberer, $team) {
            $employee = new Employee([...$request->employee(), 'created_by' => $request->user()->id]);
            $employee->team_id = $team->id;
            $employee->code = filled($request->validated('code'))
                ? (string) $request->validated('code')
                : $numberer->next($team, DocumentType::Employee, now());
            $employee->save();

            $this->saveItems($employee, $request->items());

            return $employee;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to the payroll.', ['name' => $employee->name])]);

        return to_route('employees.show', $employee);
    }

    /**
     * Display the employee with their salary, advances and payslips.
     */
    public function show(Employee $employee): Response
    {
        $employee->load(['payItems.component', 'user:id,name,email', 'creator:id,name']);
        $team = app(CurrentTeam::class)->team();

        $payslips = $employee->payslips()->with('run:id,status,period')->limit(36)->get();

        return Inertia::render('payroll/employees/Show', [
            'employee' => EmployeeResource::make($employee),
            'estimate' => SalaryEstimate::for($employee, PayrollSettings::for($team)),
            'advances' => $employee->advances()->get()->map(fn (EmployeeAdvance $advance) => $this->advance($advance))->values(),
            'payslips' => $payslips->map(fn (Payslip $payslip) => [
                'id' => $payslip->id,
                'payroll_run_id' => $payslip->payroll_run_id,
                'number' => $payslip->number,
                'period' => $payslip->period_start->toDateString(),
                'period_label' => $payslip->periodLabel(),
                'gross' => $payslip->gross,
                'income_tax' => $payslip->income_tax,
                'net' => $payslip->net,
                'paid_on' => $payslip->paid_on?->toDateString(),
                'is_draft' => $payslip->run->status === PayrollRunStatus::Draft,
            ])->values(),
            'taxYears' => $payslips
                ->filter(fn (Payslip $payslip) => $payslip->run->status !== PayrollRunStatus::Draft)
                ->map(fn (Payslip $payslip) => TaxYear::for($payslip->period_start))
                ->unique()
                ->values(),
            'attachments' => AttachmentResource::collection($employee->attachments()->with('user:id,name')->get()),
            'timeline' => Timeline::for($employee),
        ]);
    }

    /**
     * Show the form for editing the employee.
     */
    public function edit(Employee $employee): Response
    {
        $employee->load(['payItems.component', 'user:id,name,email']);

        return Inertia::render('payroll/employees/Edit', [
            'employee' => EmployeeResource::make($employee),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the employee and their salary (draft payrolls pick up the change when they are worked out again).
     */
    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        DB::transaction(function () use ($request, $employee) {
            $before = $this->itemsSummary($employee);

            $employee->fill($request->employee());

            if (filled($request->validated('code'))) {
                $employee->code = (string) $request->validated('code');
            }

            $employee->save();
            $this->saveItems($employee, $request->items());

            if ($this->itemsSummary($employee) !== $before) {
                $employee->logActivity('salary', 'Changed the allowances and deductions of the salary');
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.show', $employee);
    }

    /**
     * Remove the employee (only when no payslip was made for them).
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        if (Payslip::query()->where('employee_id', $employee->id)->exists()) {
            throw ValidationException::withMessages(['employee' => 'Payslips were made for this employee, so they are kept. Fill in a leaving date instead.']);
        }

        DB::transaction(function () use ($employee) {
            EmployeeAdvance::query()->where('employee_id', $employee->id)->delete();
            $employee->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee removed.')]);

        return to_route('employees.index');
    }

    /**
     * Download the employees with their salary and bank details as CSV (for Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $settings = PayrollSettings::for(app(CurrentTeam::class)->team());
        $query = $this->filteredQuery($this->filters($request))->with('payItems.component')->orderBy('name');

        return response()->streamDownload(function () use ($query, $settings) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Code', 'Name', 'Father / husband name', 'CNIC', 'Designation', 'Department', 'Employment', 'Joining date',
                'Leaving date', 'Phone', 'E-mail', 'Basic salary', 'Gross salary (month)', 'Income tax (month, estimate)',
                'Net salary (month, estimate)', 'Paid by', 'Bank', 'Account title', 'Account / IBAN', 'EOBI no.',
            ], ',', '"', '');

            $query->chunk(200, function (Collection $employees) use ($out, $settings) {
                foreach ($employees as $employee) {
                    /** @var Employee $employee */
                    $estimate = SalaryEstimate::for($employee, $settings);

                    fputcsv($out, [
                        $employee->code,
                        $employee->name,
                        $employee->father_name,
                        $employee->cnic,
                        $employee->designation,
                        $employee->department,
                        $employee->employment_type->label(),
                        $employee->joining_date->toDateString(),
                        $employee->leaving_date?->toDateString(),
                        $employee->phone,
                        $employee->email,
                        $employee->basic_salary,
                        $estimate['gross'],
                        $estimate['income_tax'],
                        $estimate['net'],
                        $employee->payment_method->label(),
                        $employee->bank_name,
                        $employee->bank_account_title,
                        $employee->bank_account_number,
                        $employee->eobi_number,
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'employees-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Show the certificate of the salary paid and the income tax deducted in a tax year (for the
     * employee's income tax return).
     */
    public function taxCertificate(Employee $employee, int $taxYear): HttpResponse
    {
        $payslips = $employee->payslips()
            ->whereDate('period_start', '>=', TaxYear::start($taxYear))
            ->whereDate('period_start', '<=', TaxYear::end($taxYear))
            ->whereHas('run', fn (Builder $query) => $query->where('status', '!=', PayrollRunStatus::Draft))
            ->reorder('period_start')
            ->get();

        abort_if($payslips->isEmpty(), 404);

        $team = app(CurrentTeam::class)->team();
        $department = $team->defaultDepartment();

        // The salary paid and the tax deducted in the tax year before the payroll was used.
        $opening = $employee->opening_tax_year === $taxYear
            ? ['taxable' => (string) $employee->opening_taxable, 'tax' => (string) $employee->opening_tax]
            : null;

        $sum = fn (string $field, ?string $start = null): string => (string) $payslips->reduce(
            fn (BigDecimal $total, Payslip $payslip) => $total->plus(BigDecimal::of($payslip->getAttribute($field))),
            BigDecimal::of($start ?? '0'),
        )->toScale(2);

        $pdf = Pdf::loadView('pdf.tax-certificate', [
            'employee' => $employee,
            'payslips' => $payslips,
            'department' => $department,
            'logo' => $department?->logoDataUri(),
            'options' => DocumentOptions::forTeam($team),
            'currency' => $team->teamSettings()->currency(),
            'taxYear' => TaxYear::label($taxYear),
            'opening' => $opening,
            'totals' => [
                'gross' => $sum('gross'),
                'taxable' => $sum('taxable_income', $opening['taxable'] ?? null),
                'tax' => $sum('income_tax', $opening['tax'] ?? null),
            ],
            'issuedOn' => CarbonImmutable::today(),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug('Salary tax certificate '.$taxYear.' '.$employee->name).'.pdf"',
        ]);
    }

    /**
     * Read the list filters from the request.
     *
     * @return array{search: string, status: string, department: string|null}
     */
    protected function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($status, self::STATUS_FILTERS, true) ? $status : 'current',
            'department' => $request->string('department')->trim()->toString() ?: null,
        ];
    }

    /**
     * Get the employees matching the filters.
     *
     * @param  array{search: string, status: string, department: string|null}  $filters
     * @return Builder<Employee>
     */
    protected function filteredQuery(array $filters): Builder
    {
        $today = CarbonImmutable::today();

        return Employee::query()
            ->search($filters['search'])
            ->when($filters['status'] === 'current', fn (Builder $query) => $query->current())
            ->when($filters['status'] === 'left', fn (Builder $query) => $query->whereNotNull('leaving_date')->whereDate('leaving_date', '<', $today))
            ->when($filters['department'], fn (Builder $query, string $department) => $query->where('department', $department));
    }

    /**
     * Replace the allowances, deductions and employer contributions of the salary.
     *
     * @param  list<array{pay_component_id: int, value: string}>  $items
     */
    protected function saveItems(Employee $employee, array $items): void
    {
        $employee->payItems()->delete();

        foreach ($items as $sort => $item) {
            $payItem = new EmployeePayItem([...$item, 'sort' => $sort]);
            $payItem->team_id = $employee->team_id;
            $employee->payItems()->save($payItem);
        }

        $employee->unsetRelation('payItems');
    }

    /**
     * A comparable summary of the salary items (to log changes).
     */
    protected function itemsSummary(Employee $employee): string
    {
        return $employee->payItems()->get(['pay_component_id', 'value'])
            ->map(fn (EmployeePayItem $item) => $item->pay_component_id.':'.$item->value)
            ->implode('|');
    }

    /**
     * An advance with what was recovered of it.
     *
     * @return array<string, mixed>
     */
    protected function advance(EmployeeAdvance $advance): array
    {
        $recovered = $advance->recovered();
        $scheduled = $advance->recovered(includeDrafts: true)->minus($recovered);

        return [
            'id' => $advance->id,
            'date' => $advance->date->toDateString(),
            'amount' => $advance->amount,
            'installment' => $advance->installment,
            'reason' => $advance->reason,
            'recovered' => (string) $recovered,
            'scheduled' => (string) $scheduled,
            'outstanding' => (string) $advance->outstanding(),
            'can_delete' => $recovered->isZero(),
        ];
    }

    /**
     * The distinct values of a text column (for suggestions and filters).
     *
     * @return list<string>
     */
    protected function distinct(string $column): array
    {
        return array_values(Employee::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $value) => (string) $value)
            ->all());
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'components' => PayComponent::query()->orderBy('sort')->orderBy('name')->get()->map(fn (PayComponent $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'kind' => $component->kind->value,
                'amount_type' => $component->amount_type->value,
                'default_value' => $component->default_value,
                'tax_label' => $component->taxLabel(),
                'is_active' => $component->is_active,
            ])->values(),
            'employmentTypes' => EmploymentType::options(),
            'paymentMethods' => array_values(array_filter(
                PaymentMethod::options(),
                fn (array $method) => in_array($method['value'], [PaymentMethod::BankTransfer->value, PaymentMethod::Cash->value, PaymentMethod::Cheque->value, PaymentMethod::Online->value], true),
            )),
            'taxModes' => SalaryTaxMode::options(),
            'members' => TeamOptions::users(),
            'departments' => $this->distinct('department'),
            'designations' => $this->distinct('designation'),
        ];
    }
}
