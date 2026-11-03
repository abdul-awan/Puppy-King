<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum Module: string
{
    use EnumOptions;

    case Crm = 'crm';
    case Deals = 'deals';
    case Quotations = 'quotations';
    case Invoicing = 'invoicing';
    case Expenses = 'expenses';
    case DailyExpenses = 'daily_expenses';
    case Payroll = 'payroll';
    case Products = 'products';
    case Projects = 'projects';
    case TimeTracking = 'time_tracking';
    case Planning = 'planning';
    case Tickets = 'tickets';
    case Calendar = 'calendar';
    case WorkOrders = 'work_orders';
    case Reports = 'reports';
    case Settings = 'settings';

    /**
     * Get the display label for the module.
     */
    public function label(): string
    {
        return match ($this) {
            self::Crm => 'Contacts & companies',
            self::Deals => 'Deals',
            self::Quotations => 'Quotations',
            self::Invoicing => 'Invoicing',
            self::Expenses => 'Expenses',
            self::DailyExpenses => 'Daily expenses',
            self::Payroll => 'Payroll',
            self::Products => 'Products',
            self::Projects => 'Projects',
            self::TimeTracking => 'Time tracking',
            self::Planning => 'Planning',
            self::Tickets => 'Tickets',
            self::Calendar => 'Calendar',
            self::WorkOrders => 'Work orders',
            self::Reports => 'Reports',
            self::Settings => 'Company settings',
        };
    }

    /**
     * Get the access level a regular member gets when none was configured.
     */
    public function defaultMemberAccess(): AccessLevel
    {
        // Salaries are private: members only see payroll when an owner or admin allows it.
        return match ($this) {
            self::Settings, self::Payroll => AccessLevel::None,
            self::Reports => AccessLevel::Read,
            default => AccessLevel::Write,
        };
    }
}
