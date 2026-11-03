<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum EmailTemplateType: string
{
    use EnumOptions;

    case Quotation = 'quotation';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case PaymentReminder = 'payment_reminder';
    case TicketReply = 'ticket_reply';
    case WorkOrder = 'work_order';
    case Payslip = 'payslip';

    /**
     * Get the display label for the template type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Quotation => 'Quotation',
            self::Invoice => 'Invoice',
            self::CreditNote => 'Credit note',
            self::PaymentReminder => 'Payment reminder',
            self::TicketReply => 'Ticket reply',
            self::WorkOrder => 'Work order',
            self::Payslip => 'Payslip',
        };
    }

    /**
     * Get the placeholders that can be used in templates of this type.
     *
     * @return array<int, string>
     */
    public function placeholders(): array
    {
        $common = ['{customer_name}', '{contact_name}', '{company_name}', '{user_name}'];

        return match ($this) {
            self::Quotation => [...$common, '{quotation_number}', '{quotation_total}', '{valid_until}', '{link}'],
            self::Invoice => [...$common, '{invoice_number}', '{invoice_total}', '{due_date}', '{link}'],
            self::CreditNote => [...$common, '{credit_note_number}', '{credit_note_total}', '{invoice_number}', '{link}'],
            self::PaymentReminder => [...$common, '{invoice_number}', '{amount_due}', '{due_date}', '{days_overdue}', '{link}'],
            self::TicketReply => [...$common, '{ticket_number}', '{ticket_subject}', '{message}', '{link}'],
            self::WorkOrder => [...$common, '{work_order_number}', '{work_order_title}', '{link}'],
            // Payslips go to employees, not customers.
            self::Payslip => ['{employee_name}', '{period}', '{net_pay}', '{payslip_number}', '{company_name}', '{user_name}'],
        };
    }
}
