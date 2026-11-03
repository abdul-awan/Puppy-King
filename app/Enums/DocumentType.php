<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * Document types that receive a number from a numbering sequence.
 */
enum DocumentType: string
{
    use EnumOptions;

    case Quotation = 'quotation';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Project = 'project';
    case Ticket = 'ticket';
    case WorkOrder = 'work_order';
    case Expense = 'expense';
    case DailyExpense = 'daily_expense';
    case Employee = 'employee';
    case Payslip = 'payslip';

    /**
     * Get the display label for the document type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Quotation => 'Quotations',
            self::Invoice => 'Invoices',
            self::CreditNote => 'Credit notes',
            self::Project => 'Projects',
            self::Ticket => 'Tickets',
            self::WorkOrder => 'Work orders',
            self::Expense => 'Expenses',
            self::DailyExpense => 'Daily expenses',
            self::Employee => 'Employee codes',
            self::Payslip => 'Payslips',
        };
    }

    /**
     * Get the default number pattern for the document type.
     */
    public function defaultPattern(): string
    {
        return match ($this) {
            self::Quotation => 'QT-{FYS}-{SEQ}',
            self::Invoice => 'INV-{FYS}-{SEQ}',
            self::CreditNote => 'CN-{FYS}-{SEQ}',
            self::Project => 'PRJ-{SEQ}',
            self::Ticket => 'TKT-{SEQ}',
            self::WorkOrder => 'WO-{FYS}-{SEQ}',
            self::Expense => 'EXP-{FYS}-{SEQ}',
            self::DailyExpense => 'DE-{FYS}-{SEQ}',
            self::Employee => 'EMP-{SEQ}',
            self::Payslip => 'PSL-{FYS}-{SEQ}',
        };
    }

    /**
     * Determine if each department keeps its own counter for this type.
     */
    public function numberedPerDepartment(): bool
    {
        return in_array($this, [self::Quotation, self::Invoice, self::CreditNote], true);
    }
}
