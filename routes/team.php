<?php

/*
|--------------------------------------------------------------------------
| Team routes
|--------------------------------------------------------------------------
|
| Everything in this file lives under /{current_team}/... and runs with the
| auth, verified and EnsureTeamMembership middleware (see routes/web.php).
| Each module is guarded by the "module" middleware: GET requests need view
| access, all other requests need full access.
|
*/

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Calendar\CalendarController;
use App\Http\Controllers\CompanySettings\CustomFieldController;
use App\Http\Controllers\CompanySettings\DailyExpenseCategoryController;
use App\Http\Controllers\CompanySettings\DealSourceController;
use App\Http\Controllers\CompanySettings\DepartmentController;
use App\Http\Controllers\CompanySettings\DocumentTemplateController;
use App\Http\Controllers\CompanySettings\EmailTemplateController;
use App\Http\Controllers\CompanySettings\ExpenseCategoryController;
use App\Http\Controllers\CompanySettings\GeneralSettingsController;
use App\Http\Controllers\CompanySettings\InvoiceSettingsController;
use App\Http\Controllers\CompanySettings\LostReasonController;
use App\Http\Controllers\CompanySettings\MemberAccessController;
use App\Http\Controllers\CompanySettings\NumberingController;
use App\Http\Controllers\CompanySettings\PayComponentController;
use App\Http\Controllers\CompanySettings\PaymentTermController;
use App\Http\Controllers\CompanySettings\PayrollSettingsController;
use App\Http\Controllers\CompanySettings\PipelineController;
use App\Http\Controllers\CompanySettings\ProductCategoryController;
use App\Http\Controllers\CompanySettings\SalaryTaxTableController;
use App\Http\Controllers\CompanySettings\TagController;
use App\Http\Controllers\CompanySettings\TaxRateController;
use App\Http\Controllers\CompanySettings\TemplateImageController;
use App\Http\Controllers\CompanySettings\WorkTypeController;
use App\Http\Controllers\Crm\CompanyContactController;
use App\Http\Controllers\Crm\CompanyController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\CrmExportController;
use App\Http\Controllers\Crm\CrmImportController;
use App\Http\Controllers\DailyExpenses\DailyExpenseController;
use App\Http\Controllers\DailyExpenses\PettyCashController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\Documents\QuotationController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Expenses\ExpensePaymentController;
use App\Http\Controllers\Invoicing\CreditNoteController;
use App\Http\Controllers\Invoicing\InvoiceController;
use App\Http\Controllers\Invoicing\InvoicePaymentController;
use App\Http\Controllers\Invoicing\RecurringInvoiceController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Payroll\EmployeeAdvanceController;
use App\Http\Controllers\Payroll\EmployeeController;
use App\Http\Controllers\Payroll\MyPayslipController;
use App\Http\Controllers\Payroll\PayrollRunController;
use App\Http\Controllers\Payroll\PayslipController;
use App\Http\Controllers\Planning\PlanningController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Projects\MilestoneController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectMaterialController;
use App\Http\Controllers\Projects\TaskController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Tickets\TicketController;
use App\Http\Controllers\Time\TimeEntryController;
use App\Http\Controllers\WorkOrders\WorkOrderController;
use App\Http\Controllers\WorkOrders\WorkOrderMaterialController;
use Illuminate\Support\Facades\Route;

// Shared: notes, files and search lookups (each checks the module of the record itself).
Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
Route::patch('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
Route::get('lookup/{type}', LookupController::class)->name('lookup');

// Shared: the search box and the notification bell in the header (results are filtered per module).
Route::get('search', SearchController::class)->name('search');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

// Shared: employees with a login see their own approved payslips (no access to the payroll needed).
Route::get('my-payslips', [MyPayslipController::class, 'index'])->name('my-payslips.index');
Route::get('my-payslips/{payslip}/pdf', [MyPayslipController::class, 'pdf'])->name('my-payslips.pdf');

// CRM: companies and contacts.
Route::middleware('module:crm')->group(function () {
    Route::get('companies/export', [CrmExportController::class, 'companies'])->name('companies.export');
    Route::get('contacts/export', [CrmExportController::class, 'contacts'])->name('contacts.export');

    Route::middleware('module:crm,write')->group(function () {
        Route::get('import', [CrmImportController::class, 'create'])->name('crm.import');
        Route::post('import', [CrmImportController::class, 'store'])->name('crm.import.store');
    });

    Route::patch('companies/{company}/archive', [CompanyController::class, 'archive'])->name('companies.archive');
    Route::resource('companies', CompanyController::class)
        ->middlewareFor(['create', 'edit'], 'module:crm,write');

    Route::post('companies/{company}/contacts', [CompanyContactController::class, 'store'])->name('companies.contacts.store');
    Route::patch('companies/{company}/contacts/{contact}', [CompanyContactController::class, 'update'])->name('companies.contacts.update');
    Route::delete('companies/{company}/contacts/{contact}', [CompanyContactController::class, 'destroy'])->name('companies.contacts.destroy');

    Route::patch('contacts/{contact}/archive', [ContactController::class, 'archive'])->name('contacts.archive');
    Route::resource('contacts', ContactController::class)
        ->middlewareFor(['create', 'edit'], 'module:crm,write');
});

// Deals (sales pipeline).
Route::middleware('module:deals')->group(function () {
    Route::patch('deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');
    Route::post('deals/{deal}/won', [DealController::class, 'won'])->name('deals.won');
    Route::post('deals/{deal}/lost', [DealController::class, 'lost'])->name('deals.lost');
    Route::post('deals/{deal}/reopen', [DealController::class, 'reopen'])->name('deals.reopen');
    Route::resource('deals', DealController::class)
        ->middlewareFor(['create', 'edit'], 'module:deals,write');
});

// Quotations.
Route::middleware('module:quotations')->group(function () {
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::post('quotations/{quotation}/mark-sent', [QuotationController::class, 'markSent'])->name('quotations.mark-sent');
    Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
    Route::post('quotations/{quotation}/decline', [QuotationController::class, 'decline'])->name('quotations.decline');
    Route::post('quotations/{quotation}/reopen', [QuotationController::class, 'reopen'])->name('quotations.reopen');
    Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::post('quotations/{quotation}/invoice', [QuotationController::class, 'invoice'])
        ->middleware('module:invoicing,write')
        ->name('quotations.invoice');
    Route::resource('quotations', QuotationController::class)
        ->middlewareFor(['create', 'edit'], 'module:quotations,write');
});

// Invoicing: invoices, credit notes, payments and recurring invoices.
Route::middleware('module:invoicing')->group(function () {
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
    Route::post('invoices/{invoice}/remind', [InvoiceController::class, 'remind'])->name('invoices.remind');
    Route::patch('invoices/{invoice}/fbr-number', [InvoiceController::class, 'fbrNumber'])->name('invoices.fbr-number');
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::post('invoices/{invoice}/credit', [InvoiceController::class, 'credit'])->name('invoices.credit');
    Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
    Route::delete('invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');
    Route::resource('invoices', InvoiceController::class)
        ->middlewareFor(['create', 'edit'], 'module:invoicing,write');

    Route::get('credit-notes/{credit_note}/pdf', [CreditNoteController::class, 'pdf'])->name('credit-notes.pdf');
    Route::post('credit-notes/{credit_note}/issue', [CreditNoteController::class, 'issue'])->name('credit-notes.issue');
    Route::post('credit-notes/{credit_note}/send', [CreditNoteController::class, 'send'])->name('credit-notes.send');
    Route::resource('credit-notes', CreditNoteController::class)
        ->except(['create', 'store'])
        ->middlewareFor(['edit'], 'module:invoicing,write');

    Route::get('payments', [InvoicePaymentController::class, 'index'])->name('payments.index');

    Route::post('recurring-invoices/{recurring_invoice}/generate', [RecurringInvoiceController::class, 'generate'])->name('recurring-invoices.generate');
    Route::post('recurring-invoices/{recurring_invoice}/toggle', [RecurringInvoiceController::class, 'toggle'])->name('recurring-invoices.toggle');
    Route::resource('recurring-invoices', RecurringInvoiceController::class)
        ->middlewareFor(['create', 'edit'], 'module:invoicing,write');
});

// Expenses (bills from suppliers).
Route::middleware('module:expenses')->group(function () {
    Route::get('expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::post('expenses/{expense}/payments', [ExpensePaymentController::class, 'store'])->name('expenses.payments.store');
    Route::delete('expenses/{expense}/payments/{payment}', [ExpensePaymentController::class, 'destroy'])->name('expenses.payments.destroy');
    Route::resource('expenses', ExpenseController::class)
        ->middlewareFor(['create', 'edit'], 'module:expenses,write');
});

// Daily expenses: day-to-day spending and the cash box (petty cash).
Route::middleware('module:daily_expenses')->group(function () {
    Route::get('daily-expenses/export', [DailyExpenseController::class, 'export'])->name('daily-expenses.export');
    Route::get('daily-expenses/print', [DailyExpenseController::class, 'pdf'])->name('daily-expenses.pdf');
    Route::get('daily-expenses/cash-book', [PettyCashController::class, 'index'])->name('cash-book.index');
    Route::get('daily-expenses/cash-book/export', [PettyCashController::class, 'export'])->name('cash-book.export');
    Route::post('daily-expenses/cash-book', [PettyCashController::class, 'store'])->name('cash-book.store');
    Route::put('daily-expenses/cash-book/{petty_cash_entry}', [PettyCashController::class, 'update'])->name('cash-book.update');
    Route::delete('daily-expenses/cash-book/{petty_cash_entry}', [PettyCashController::class, 'destroy'])->name('cash-book.destroy');
    Route::resource('daily-expenses', DailyExpenseController::class)->except(['create', 'edit']);
});

// Payroll: employees, the monthly payroll and payslips.
Route::middleware('module:payroll')->group(function () {
    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('employees/{employee}/tax-certificate/{tax_year}', [EmployeeController::class, 'taxCertificate'])
        ->whereNumber('tax_year')
        ->name('employees.tax-certificate');
    Route::post('employees/{employee}/advances', [EmployeeAdvanceController::class, 'store'])->name('employees.advances.store');
    Route::put('advances/{advance}', [EmployeeAdvanceController::class, 'update'])->name('advances.update');
    Route::delete('advances/{advance}', [EmployeeAdvanceController::class, 'destroy'])->name('advances.destroy');
    Route::resource('employees', EmployeeController::class)
        ->middlewareFor(['create', 'edit'], 'module:payroll,write');

    Route::get('payroll', [PayrollRunController::class, 'index'])->name('payroll.index');
    Route::post('payroll', [PayrollRunController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{payroll_run}', [PayrollRunController::class, 'show'])->name('payroll.show');
    Route::patch('payroll/{payroll_run}', [PayrollRunController::class, 'update'])->name('payroll.update');
    Route::delete('payroll/{payroll_run}', [PayrollRunController::class, 'destroy'])->name('payroll.destroy');
    Route::post('payroll/{payroll_run}/recalculate', [PayrollRunController::class, 'recalculate'])->name('payroll.recalculate');
    Route::post('payroll/{payroll_run}/employees', [PayrollRunController::class, 'addEmployee'])->name('payroll.employees.store');
    Route::post('payroll/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->name('payroll.approve');
    Route::post('payroll/{payroll_run}/reopen', [PayrollRunController::class, 'reopen'])->name('payroll.reopen');
    Route::post('payroll/{payroll_run}/pay', [PayrollRunController::class, 'pay'])->name('payroll.pay');
    Route::delete('payroll/{payroll_run}/pay', [PayrollRunController::class, 'unpay'])->name('payroll.unpay');
    Route::post('payroll/{payroll_run}/email', [PayrollRunController::class, 'email'])->name('payroll.email');
    Route::get('payroll/{payroll_run}/pdf', [PayrollRunController::class, 'pdf'])->name('payroll.pdf');
    Route::get('payroll/{payroll_run}/register', [PayrollRunController::class, 'register'])->name('payroll.register');
    Route::get('payroll/{payroll_run}/bank-transfers', [PayrollRunController::class, 'bankTransfers'])->name('payroll.bank-transfers');

    Route::put('payslips/{payslip}', [PayslipController::class, 'update'])->name('payslips.update');
    Route::delete('payslips/{payslip}', [PayslipController::class, 'destroy'])->name('payslips.destroy');
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf'])->name('payslips.pdf');
    Route::post('payslips/{payslip}/email', [PayslipController::class, 'email'])->name('payslips.email');
    Route::post('payslips/{payslip}/pay', [PayslipController::class, 'pay'])->name('payslips.pay');
    Route::delete('payslips/{payslip}/pay', [PayslipController::class, 'unpay'])->name('payslips.unpay');
});

// Projects: milestones, tasks and materials.
Route::middleware('module:projects')->group(function () {
    Route::patch('projects/{project}/status', [ProjectController::class, 'status'])->name('projects.status');
    Route::post('projects/{project}/invoice', [ProjectController::class, 'invoice'])
        ->middleware('module:invoicing,write')
        ->name('projects.invoice');
    Route::post('projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store');
    Route::put('milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
    Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');
    Route::post('projects/{project}/materials', [ProjectMaterialController::class, 'store'])->name('projects.materials.store');
    Route::put('materials/{material}', [ProjectMaterialController::class, 'update'])->name('materials.update');
    Route::delete('materials/{material}', [ProjectMaterialController::class, 'destroy'])->name('materials.destroy');
    Route::resource('projects', ProjectController::class)
        ->middlewareFor(['create', 'edit'], 'module:projects,write');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
});

// Time tracking.
Route::middleware('module:time_tracking')->group(function () {
    Route::get('time', [TimeEntryController::class, 'index'])->name('time.index');
    Route::get('time/export', [TimeEntryController::class, 'export'])->name('time.export');
    Route::post('time', [TimeEntryController::class, 'store'])->name('time.store');
    Route::put('time/{time_entry}', [TimeEntryController::class, 'update'])->name('time.update');
    Route::delete('time/{time_entry}', [TimeEntryController::class, 'destroy'])->name('time.destroy');
    Route::post('timer', [TimeEntryController::class, 'start'])->name('timer.start');
    Route::post('timer/stop', [TimeEntryController::class, 'stop'])->name('timer.stop');
});

// Planning: who works on what, and when.
Route::middleware('module:planning')->group(function () {
    Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::patch('planning/tasks/{task}', [PlanningController::class, 'plan'])->name('planning.plan');
});

// Tickets (helpdesk).
Route::middleware('module:tickets')->group(function () {
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('tickets/{ticket}/quick', [TicketController::class, 'quickUpdate'])->name('tickets.quick-update');
    Route::resource('tickets', TicketController::class)
        ->except('edit')
        ->middlewareFor(['create'], 'module:tickets,write');
});

// Work orders: work done for a customer (usually on site), signed by the customer.
Route::middleware('module:work_orders')->group(function () {
    Route::post('work-orders/invoice', [WorkOrderController::class, 'invoice'])
        ->middleware('module:invoicing,write')
        ->name('work-orders.invoice');
    Route::get('work-orders/{work_order}/pdf', [WorkOrderController::class, 'pdf'])->name('work-orders.pdf');
    Route::post('work-orders/{work_order}/complete', [WorkOrderController::class, 'complete'])->name('work-orders.complete');
    Route::post('work-orders/{work_order}/reopen', [WorkOrderController::class, 'reopen'])->name('work-orders.reopen');
    Route::post('work-orders/{work_order}/sign', [WorkOrderController::class, 'sign'])->name('work-orders.sign');
    Route::post('work-orders/{work_order}/send', [WorkOrderController::class, 'send'])->name('work-orders.send');
    Route::post('work-orders/{work_order}/materials', [WorkOrderMaterialController::class, 'store'])->name('work-orders.materials.store');
    Route::put('work-orders/{work_order}/materials/{material}', [WorkOrderMaterialController::class, 'update'])->name('work-orders.materials.update');
    Route::delete('work-orders/{work_order}/materials/{material}', [WorkOrderMaterialController::class, 'destroy'])->name('work-orders.materials.destroy');
    Route::resource('work-orders', WorkOrderController::class)
        ->middlewareFor(['create', 'edit'], 'module:work_orders,write');
});

// Calendar: meetings, calls and appointments.
Route::middleware('module:calendar')->group(function () {
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('calendar/{event}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::post('calendar/{event}/complete', [CalendarController::class, 'complete'])->name('calendar.complete');
    Route::delete('calendar/{event}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    Route::get('calendar/{event}/ics', [CalendarController::class, 'ics'])->name('calendar.ics');
});

// Reports (each report also needs view access to the module its figures come from).
Route::middleware('module:reports')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
    Route::get('reports/receivables', [ReportController::class, 'receivables'])->name('reports.receivables');
    Route::get('reports/sales-tax', [ReportController::class, 'salesTax'])->name('reports.sales-tax');
    Route::get('reports/pipeline', [ReportController::class, 'pipeline'])->name('reports.pipeline');
    Route::get('reports/time', [ReportController::class, 'time'])->name('reports.time');
    Route::get('reports/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
    Route::get('reports/daily-expenses', [ReportController::class, 'dailyExpenses'])->name('reports.daily-expenses');
    Route::get('reports/payroll', [ReportController::class, 'payroll'])->name('reports.payroll');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])
        ->whereIn('report', array_keys(ReportController::REPORTS))
        ->name('reports.export');
});

// Products and services.
Route::middleware('module:products')->group(function () {
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::post('products/{product}/stock', [ProductController::class, 'adjustStock'])->name('products.stock');
    Route::resource('products', ProductController::class)
        ->middlewareFor(['create', 'edit'], 'module:products,write');
});

// Company settings.
Route::prefix('settings')->name('settings.')->middleware('module:settings')->group(function () {
    Route::get('general', [GeneralSettingsController::class, 'edit'])->name('general.edit');
    Route::put('general', [GeneralSettingsController::class, 'update'])->name('general.update');

    Route::get('departments/{department}/logo', [DepartmentController::class, 'logo'])->name('departments.logo');
    Route::resource('departments', DepartmentController::class)
        ->except('show')
        ->middlewareFor(['create', 'edit'], 'module:settings,write');

    Route::resource('tax-rates', TaxRateController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('payment-terms', PaymentTermController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('work-types', WorkTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('custom-fields', CustomFieldController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('product-categories', ProductCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('deal-sources', DealSourceController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('lost-reasons', LostReasonController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('expense-categories', ExpenseCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('daily-expense-categories', DailyExpenseCategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    // Payroll settings: part months, rounding, EOBI, pay components and the salary tax slabs.
    Route::get('payroll', [PayrollSettingsController::class, 'edit'])->name('payroll.edit');
    Route::put('payroll', [PayrollSettingsController::class, 'update'])->name('payroll.update');
    Route::resource('pay-components', PayComponentController::class)->only(['store', 'update', 'destroy']);
    Route::resource('salary-tax-tables', SalaryTaxTableController::class)->only(['store', 'update', 'destroy']);

    Route::get('pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::post('pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::put('pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
    Route::delete('pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');
    Route::post('pipelines/{pipeline}/phases', [PipelineController::class, 'storePhase'])->name('pipelines.phases.store');
    Route::put('pipelines/{pipeline}/phases/order', [PipelineController::class, 'orderPhases'])->name('pipelines.phases.order');
    Route::put('phases/{phase}', [PipelineController::class, 'updatePhase'])->name('phases.update');
    Route::delete('phases/{phase}', [PipelineController::class, 'destroyPhase'])->name('phases.destroy');

    Route::get('numbering', [NumberingController::class, 'edit'])->name('numbering.edit');
    Route::put('numbering', [NumberingController::class, 'update'])->name('numbering.update');

    // Invoice settings: templates of quotations, invoices and credit notes and what they show.
    Route::prefix('invoice-settings')->group(function () {
        Route::get('/', [InvoiceSettingsController::class, 'edit'])->name('invoice-settings.edit');
        Route::put('/', [InvoiceSettingsController::class, 'update'])->name('invoice-settings.update');
        Route::get('preview', [InvoiceSettingsController::class, 'preview'])
            ->middleware('throttle:60,1')
            ->name('invoice-settings.preview');
        Route::post('templates/preview', [DocumentTemplateController::class, 'preview'])
            ->middleware('throttle:60,1')
            ->name('invoice-templates.preview');
        Route::post('template-images', [TemplateImageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('template-images.store');
        Route::get('template-images/{name}', [TemplateImageController::class, 'show'])
            ->where('name', '[A-Za-z0-9-]{8,64}\.(png|jpe?g|gif)')
            ->name('template-images.show');
        Route::resource('templates', DocumentTemplateController::class)
            ->except(['index', 'show'])
            ->names('invoice-templates')
            ->parameters(['templates' => 'documentTemplate'])
            ->middlewareFor(['create', 'edit'], 'module:settings,write');
    });

    Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::put('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');

    Route::get('members', [MemberAccessController::class, 'index'])->name('members.index');
    Route::put('members/{user}', [MemberAccessController::class, 'update'])->name('members.update');
});
