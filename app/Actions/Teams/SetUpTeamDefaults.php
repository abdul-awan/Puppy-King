<?php

namespace App\Actions\Teams;

use App\Enums\EmailTemplateType;
use App\Enums\TaxAuthority;
use App\Enums\TaxKind;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Gives a new team (company workspace) sensible defaults for Pakistan.
 *
 * Every step only runs when the team has no records of that kind yet, so the
 * action can safely be executed again for existing teams.
 */
class SetUpTeamDefaults
{
    /**
     * Create the default records for the team.
     */
    public function handle(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $this->department($team);
            $this->taxRates($team);
            $this->paymentTerms($team);
            $this->workTypes($team);
            $this->emailTemplates($team);
            $this->salesPipeline($team);
            $this->expenseCategories($team);
            $this->dailyExpenseCategories($team);
            $this->payComponents($team);
            $this->salaryTaxTables($team);
        });
    }

    /**
     * Create the usual categories of day-to-day spending.
     */
    protected function dailyExpenseCategories(Team $team): void
    {
        if ($team->dailyExpenseCategories()->withoutGlobalScopes()->exists()) {
            return;
        }

        $categories = [
            'Tea & refreshments', 'Meals', 'Fuel', 'Local travel & fares', 'Stationery & printing', 'Postage & courier',
            'Mobile & internet top-ups', 'Utility bills', 'Repairs & maintenance', 'Cleaning supplies', 'Guests & entertainment',
            'Staff welfare', 'Charity & donations', 'Miscellaneous',
        ];

        foreach ($categories as $sort => $name) {
            $team->dailyExpenseCategories()->create(['name' => $name, 'sort' => $sort]);
        }
    }

    /**
     * Create the usual allowances, deductions and employer contributions of Pakistani salaries.
     */
    protected function payComponents(Team $team): void
    {
        if ($team->payComponents()->withoutGlobalScopes()->exists()) {
            return;
        }

        $components = [
            ['name' => 'House rent allowance', 'kind' => 'earning'],
            // Tax-free up to 10% of the basic salary (clause 139 of the Second Schedule).
            ['name' => 'Medical allowance', 'kind' => 'earning', 'tax_free_percent' => '10'],
            ['name' => 'Conveyance allowance', 'kind' => 'earning'],
            ['name' => 'Utilities allowance', 'kind' => 'earning'],
            ['name' => 'Fuel allowance', 'kind' => 'earning'],
            ['name' => 'Mobile allowance', 'kind' => 'earning'],
            ['name' => 'Overtime', 'kind' => 'earning'],
            ['name' => 'Bonus', 'kind' => 'earning'],
            ['name' => 'Commission', 'kind' => 'earning'],
            ['name' => 'Arrears', 'kind' => 'earning'],
            ['name' => 'Provident fund (employee share)', 'kind' => 'deduction', 'amount_type' => 'percent_basic', 'default_value' => '10'],
            ['name' => 'Fine / late coming', 'kind' => 'deduction'],
            ['name' => 'Other deduction', 'kind' => 'deduction'],
            ['name' => 'Provident fund (employer share)', 'kind' => 'employer', 'amount_type' => 'percent_basic', 'default_value' => '10'],
            ['name' => 'Social security (PESSI / SESSI)', 'kind' => 'employer', 'amount_type' => 'percent_gross', 'default_value' => '6'],
        ];

        foreach ($components as $sort => $component) {
            $team->payComponents()->create([...$component, 'sort' => $sort]);
        }
    }

    /**
     * Create the income tax slabs for salaried individuals of the recent tax years. The slabs change
     * with every budget, so companies add and check them under Company settings → Payroll.
     */
    protected function salaryTaxTables(Team $team): void
    {
        if ($team->salaryTaxTables()->withoutGlobalScopes()->exists()) {
            return;
        }

        $tables = [
            [
                'tax_year' => 2025,
                'name' => 'Finance Act 2024',
                'slabs' => [
                    ['from' => '0', 'fixed' => '0', 'rate' => '0'],
                    ['from' => '600000', 'fixed' => '0', 'rate' => '5'],
                    ['from' => '1200000', 'fixed' => '30000', 'rate' => '15'],
                    ['from' => '2200000', 'fixed' => '180000', 'rate' => '25'],
                    ['from' => '3200000', 'fixed' => '430000', 'rate' => '30'],
                    ['from' => '4100000', 'fixed' => '700000', 'rate' => '35'],
                ],
                'surcharge_threshold' => '10000000',
                'surcharge_percent' => '10',
            ],
            [
                'tax_year' => 2026,
                'name' => 'Finance Act 2025',
                'slabs' => [
                    ['from' => '0', 'fixed' => '0', 'rate' => '0'],
                    ['from' => '600000', 'fixed' => '0', 'rate' => '1'],
                    ['from' => '1200000', 'fixed' => '6000', 'rate' => '11'],
                    ['from' => '2200000', 'fixed' => '116000', 'rate' => '23'],
                    ['from' => '3200000', 'fixed' => '346000', 'rate' => '30'],
                    ['from' => '4100000', 'fixed' => '616000', 'rate' => '35'],
                ],
                'surcharge_threshold' => '10000000',
                'surcharge_percent' => '9',
            ],
        ];

        foreach ($tables as $table) {
            $team->salaryTaxTables()->create($table);
        }
    }

    /**
     * Create the usual expense categories of a Pakistani business.
     */
    protected function expenseCategories(Team $team): void
    {
        if ($team->expenseCategories()->withoutGlobalScopes()->exists()) {
            return;
        }

        $categories = [
            'Purchases (stock / raw material)', 'Rent', 'Electricity, gas & water', 'Internet & phone',
            'Salaries & wages', 'Fuel & travel', 'Office supplies', 'Repairs & maintenance', 'Professional fees',
            'Marketing & advertising', 'Software & subscriptions', 'Bank charges', 'Freight & courier', 'Other',
        ];

        foreach ($categories as $sort => $name) {
            $team->expenseCategories()->create(['name' => $name, 'sort' => $sort]);
        }
    }

    /**
     * Create a default sales pipeline with phases, deal sources and lost reasons.
     */
    protected function salesPipeline(Team $team): void
    {
        if (! $team->dealPipelines()->withoutGlobalScopes()->exists()) {
            $pipeline = $team->dealPipelines()->create(['name' => 'Sales', 'is_default' => true]);

            $phases = [
                ['New lead', 10],
                ['Contacted', 20],
                ['Qualified', 40],
                ['Proposal sent', 60],
                ['Negotiation', 80],
            ];

            foreach ($phases as $sort => [$name, $probability]) {
                $pipeline->phases()->create([
                    'team_id' => $team->id,
                    'name' => $name,
                    'probability' => $probability,
                    'sort' => $sort,
                ]);
            }
        }

        if (! $team->dealSources()->withoutGlobalScopes()->exists()) {
            foreach (['Website', 'Referral', 'Phone call', 'E-mail', 'Social media', 'Exhibition / event', 'Existing customer', 'Other'] as $sort => $name) {
                $team->dealSources()->create(['name' => $name, 'sort' => $sort]);
            }
        }

        if (! $team->lostReasons()->withoutGlobalScopes()->exists()) {
            foreach (['Price too high', 'Chose a competitor', 'No budget', 'No response', 'Bad timing', 'Requirements not met', 'Other'] as $sort => $name) {
                $team->lostReasons()->create(['name' => $name, 'sort' => $sort]);
            }
        }
    }

    /**
     * Create the company profile (default department).
     */
    protected function department(Team $team): void
    {
        if ($team->departments()->withoutGlobalScopes()->exists()) {
            return;
        }

        $team->departments()->create([
            'name' => $team->name,
            'country' => 'Pakistan',
            'is_default' => true,
        ]);
    }

    /**
     * Create commonly used Pakistani tax rates. Rates change with each budget,
     * so teams are asked to review them in the settings.
     */
    protected function taxRates(Team $team): void
    {
        if ($team->taxRates()->withoutGlobalScopes()->exists()) {
            return;
        }

        $rates = [
            ['name' => 'Sales tax 18%', 'rate' => '18', 'kind' => TaxKind::Standard, 'authority' => TaxAuthority::Fbr, 'is_default' => true, 'description' => 'Standard federal sales tax on goods'],
            ['name' => 'Zero-rated 0%', 'rate' => '0', 'kind' => TaxKind::ZeroRated, 'authority' => TaxAuthority::Fbr, 'description' => 'Exports and other zero-rated supplies'],
            ['name' => 'Exempt', 'rate' => '0', 'kind' => TaxKind::Exempt, 'authority' => null, 'description' => 'Supplies exempt from sales tax'],
            ['name' => 'Punjab services 16%', 'rate' => '16', 'kind' => TaxKind::Standard, 'authority' => TaxAuthority::Pra, 'description' => 'Punjab sales tax on services'],
            ['name' => 'Sindh services 15%', 'rate' => '15', 'kind' => TaxKind::Standard, 'authority' => TaxAuthority::Srb, 'description' => 'Sindh sales tax on services'],
            ['name' => 'KP services 15%', 'rate' => '15', 'kind' => TaxKind::Standard, 'authority' => TaxAuthority::Kpra, 'description' => 'Khyber Pakhtunkhwa sales tax on services'],
            ['name' => 'Balochistan services 15%', 'rate' => '15', 'kind' => TaxKind::Standard, 'authority' => TaxAuthority::Bra, 'description' => 'Balochistan sales tax on services'],
            ['name' => 'Islamabad services 16%', 'rate' => '16', 'kind' => TaxKind::Standard, 'authority' => TaxAuthority::Ict, 'description' => 'Islamabad Capital Territory sales tax on services'],
        ];

        foreach ($rates as $sort => $rate) {
            $team->taxRates()->create([...$rate, 'sort' => $sort]);
        }
    }

    /**
     * Create the usual payment terms.
     */
    protected function paymentTerms(Team $team): void
    {
        if ($team->paymentTerms()->withoutGlobalScopes()->exists()) {
            return;
        }

        $terms = [
            ['name' => 'Due on receipt', 'days' => 0],
            ['name' => '7 days', 'days' => 7],
            ['name' => '15 days', 'days' => 15],
            ['name' => '30 days', 'days' => 30, 'is_default' => true],
            ['name' => '45 days', 'days' => 45],
            ['name' => '60 days', 'days' => 60],
            ['name' => 'End of month', 'days' => 0, 'end_of_month' => true],
            ['name' => '30 days after end of month', 'days' => 30, 'end_of_month' => true],
        ];

        foreach ($terms as $sort => $term) {
            $team->paymentTerms()->create([...$term, 'sort' => $sort]);
        }
    }

    /**
     * Create a few general work types for time tracking.
     */
    protected function workTypes(Team $team): void
    {
        if ($team->workTypes()->withoutGlobalScopes()->exists()) {
            return;
        }

        foreach (['General', 'Consulting', 'Development', 'Support', 'Installation', 'Travel'] as $sort => $name) {
            $team->workTypes()->create([
                'name' => $name,
                'is_billable' => $name !== 'Travel',
                'sort' => $sort,
            ]);
        }
    }

    /**
     * Create the default e-mail templates (of each kind the team does not have yet).
     */
    protected function emailTemplates(Team $team): void
    {
        $existing = $team->emailTemplates()->withoutGlobalScopes()->pluck('type')
            ->map(fn (mixed $type) => $type instanceof EmailTemplateType ? $type->value : (string) $type)
            ->all();

        $templates = [
            EmailTemplateType::Quotation->value => [
                'Quotation {quotation_number} from {company_name}',
                "Dear {contact_name},\n\nPlease find attached our quotation {quotation_number} for {quotation_total}. The quotation is valid until {valid_until}.\n\nYou can view and accept it online: {link}\n\nKind regards,\n{user_name}\n{company_name}",
            ],
            EmailTemplateType::Invoice->value => [
                'Invoice {invoice_number} from {company_name}',
                "Dear {contact_name},\n\nPlease find attached invoice {invoice_number} for {invoice_total}, due on {due_date}.\n\nYou can also view it online: {link}\n\nKind regards,\n{user_name}\n{company_name}",
            ],
            EmailTemplateType::CreditNote->value => [
                'Credit note {credit_note_number} from {company_name}',
                "Dear {contact_name},\n\nPlease find attached credit note {credit_note_number} for {credit_note_total}, relating to invoice {invoice_number}.\n\nKind regards,\n{user_name}\n{company_name}",
            ],
            EmailTemplateType::PaymentReminder->value => [
                'Payment reminder: invoice {invoice_number}',
                "Dear {contact_name},\n\nOur records show that invoice {invoice_number} of {amount_due} was due on {due_date} ({days_overdue} days ago) and has not been paid yet.\n\nIf you have already paid, please ignore this message. Otherwise we kindly ask you to settle the amount as soon as possible.\n\nView the invoice: {link}\n\nKind regards,\n{company_name}",
            ],
            EmailTemplateType::TicketReply->value => [
                'Re: [{ticket_number}] {ticket_subject}',
                "{message}\n\n---\nView the full conversation and reply: {link}\n{company_name}",
            ],
            EmailTemplateType::WorkOrder->value => [
                'Work order {work_order_number}: {work_order_title}',
                "Dear {contact_name},\n\nPlease find attached work order {work_order_number}.\n\nKind regards,\n{user_name}\n{company_name}",
            ],
            EmailTemplateType::Payslip->value => [
                'Your payslip for {period} - {company_name}',
                "Dear {employee_name},\n\nPlease find attached your payslip {payslip_number} for {period}. Your net pay is {net_pay}.\n\nKind regards,\n{user_name}\n{company_name}",
            ],
        ];

        foreach ($templates as $type => [$subject, $body]) {
            if (in_array($type, $existing, true)) {
                continue;
            }

            $team->emailTemplates()->create([
                'type' => $type,
                'name' => EmailTemplateType::from($type)->label(),
                'subject' => $subject,
                'body' => $body,
                'is_default' => true,
            ]);
        }
    }
}
