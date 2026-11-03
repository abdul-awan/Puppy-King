<?php

namespace App\Support;

use App\Enums\Module;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\DailyExpense;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PayrollRun;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Registry of record types that can have notes, attachments and activity.
 *
 * The keys double as the polymorphic morph-map aliases stored in the database.
 */
final class RecordTypes
{
    /**
     * Get all registered record types.
     *
     * @return array<string, array{model: class-string<Model>, module: Module, label: string}>
     */
    public static function all(): array
    {
        return [
            'company' => ['model' => Company::class, 'module' => Module::Crm, 'label' => 'Company'],
            'contact' => ['model' => Contact::class, 'module' => Module::Crm, 'label' => 'Contact'],
            'product' => ['model' => Product::class, 'module' => Module::Products, 'label' => 'Product'],
            'deal' => ['model' => Deal::class, 'module' => Module::Deals, 'label' => 'Deal'],
            'quotation' => ['model' => Quotation::class, 'module' => Module::Quotations, 'label' => 'Quotation'],
            'invoice' => ['model' => Invoice::class, 'module' => Module::Invoicing, 'label' => 'Invoice'],
            'credit_note' => ['model' => CreditNote::class, 'module' => Module::Invoicing, 'label' => 'Credit note'],
            'recurring_invoice' => ['model' => RecurringInvoice::class, 'module' => Module::Invoicing, 'label' => 'Recurring invoice'],
            'expense' => ['model' => Expense::class, 'module' => Module::Expenses, 'label' => 'Expense'],
            'daily_expense' => ['model' => DailyExpense::class, 'module' => Module::DailyExpenses, 'label' => 'Daily expense'],
            'employee' => ['model' => Employee::class, 'module' => Module::Payroll, 'label' => 'Employee'],
            'payroll_run' => ['model' => PayrollRun::class, 'module' => Module::Payroll, 'label' => 'Payroll'],
            'project' => ['model' => Project::class, 'module' => Module::Projects, 'label' => 'Project'],
            'task' => ['model' => Task::class, 'module' => Module::Projects, 'label' => 'Task'],
            'ticket' => ['model' => Ticket::class, 'module' => Module::Tickets, 'label' => 'Ticket'],
            'work_order' => ['model' => WorkOrder::class, 'module' => Module::WorkOrders, 'label' => 'Work order'],
        ];
    }

    /**
     * Get the morph map (alias => model class).
     *
     * @return array<string, class-string<Model>>
     */
    public static function morphMap(): array
    {
        return array_map(fn (array $type) => $type['model'], self::all());
    }

    /**
     * Get the module that guards the given record type.
     */
    public static function module(string $alias): Module
    {
        return self::all()[$alias]['module'] ?? abort(404);
    }

    /**
     * Find a record of the given type in the current team or fail.
     */
    public static function find(string $alias, int $id): Model
    {
        $model = self::all()[$alias]['model'] ?? abort(404);

        return $model::query()->findOrFail($id);
    }

    /**
     * Validation rule accepting only registered aliases.
     */
    public static function rule(): In
    {
        return Rule::in(array_keys(self::all()));
    }
}
