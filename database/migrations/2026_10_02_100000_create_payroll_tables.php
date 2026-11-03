<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Allowances, deductions and employer contributions the company uses on payslips.
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->string('amount_type', 20)->default('fixed');
            $table->decimal('default_value', 15, 2)->default(0);
            $table->boolean('taxable')->default(true);
            $table->decimal('tax_free_percent', 6, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'sort']);
        });

        // The income tax slabs for salaried individuals, per tax year (July to June).
        Schema::create('salary_tax_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->string('name', 100);
            $table->json('slabs');
            $table->decimal('surcharge_threshold', 15, 2)->nullable();
            $table->decimal('surcharge_percent', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'tax_year']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('father_name', 150)->nullable();
            $table->string('cnic', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('employment_type', 20)->default('permanent');
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('payment_method', 20)->default('bank_transfer');
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_title', 150)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('tax_mode', 20)->default('auto');
            $table->decimal('fixed_tax', 15, 2)->nullable();
            $table->boolean('eobi')->default(false);
            $table->string('eobi_number', 30)->nullable();
            // The employee's own login (a member of the company), to see their payslips.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'name']);
        });

        // The allowances, deductions and employer contributions on an employee's monthly salary.
        Schema::create('employee_pay_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 15, 2);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Salary advances and loans, recovered in monthly installments through the payroll.
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->decimal('installment', 15, 2);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'employee_id']);
        });

        // One payroll per month.
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->date('pay_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('employees_count')->default(0);
            $table->decimal('gross', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('income_tax', 15, 2)->default(0);
            $table->decimal('net', 15, 2)->default(0);
            $table->decimal('employer_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'period']);
        });

        // The payslip of one employee in a payroll (the employee's details are kept as they were).
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('number', 50)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('employee_name', 150);
            $table->string('employee_code', 30);
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('cnic', 20)->nullable();
            $table->string('payment_method', 20)->default('bank_transfer');
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_title', 150)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->unsignedSmallInteger('days_in_period')->default(0);
            $table->unsignedSmallInteger('employed_days')->default(0);
            $table->decimal('unpaid_days', 5, 2)->default(0);
            $table->decimal('payable_days', 5, 2)->default(0);
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('gross', 15, 2)->default(0);
            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('income_tax', 15, 2)->default(0);
            $table->decimal('tax_override', 15, 2)->nullable();
            $table->json('tax_details')->nullable();
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('employer_contributions', 15, 2)->default(0);
            $table->decimal('net', 15, 2)->default(0);
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->unique(['team_id', 'number']);
            $table->index(['team_id', 'employee_id', 'period_start']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_advance_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('kind', 20);
            $table->string('name', 100);
            $table->decimal('amount', 15, 2);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('employee_pay_items');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('salary_tax_tables');
        Schema::dropIfExists('pay_components');
    }
};
