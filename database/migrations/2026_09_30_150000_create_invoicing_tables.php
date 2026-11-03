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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('balance', 15, 3);
            $table->string('reason')->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['team_id', 'product_id', 'created_at']);
        });

        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency', 20)->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_date')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->boolean('auto_issue')->default(false);
            $table->boolean('auto_send')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('currency', 3)->default('PKR');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('further_tax_rate', 6, 3)->nullable();
            $table->decimal('further_tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('introduction')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'next_date']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50)->nullable();
            $table->string('title')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recurring_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3)->default('PKR');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('further_tax_rate', 6, 3)->nullable();
            $table->decimal('further_tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_credited', 15, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_ntn', 20)->nullable();
            $table->string('customer_strn', 20)->nullable();
            $table->string('customer_cnic', 15)->nullable();
            $table->boolean('customer_registered')->default(false);
            $table->string('fbr_invoice_number', 100)->nullable();
            $table->text('introduction')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->string('public_token', 64)->unique();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedSmallInteger('reminder_count')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'number']);
            $table->index(['team_id', 'status', 'due_date']);
            $table->index(['team_id', 'date']);
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50)->nullable();
            $table->string('title')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('draft');
            $table->string('reason')->nullable();
            $table->boolean('restock')->default(false);
            $table->string('currency', 3)->default('PKR');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('further_tax_rate', 6, 3)->nullable();
            $table->decimal('further_tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_ntn', 20)->nullable();
            $table->string('customer_strn', 20)->nullable();
            $table->string('customer_cnic', 15)->nullable();
            $table->boolean('customer_registered')->default(false);
            $table->text('introduction')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->string('public_token', 64)->unique();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'number']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->decimal('income_tax_withheld', 15, 2)->default(0);
            $table->decimal('sales_tax_withheld', 15, 2)->default(0);
            $table->string('method', 20)->default('bank_transfer');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('recurring_invoices');
        Schema::dropIfExists('stock_movements');
    }
};
