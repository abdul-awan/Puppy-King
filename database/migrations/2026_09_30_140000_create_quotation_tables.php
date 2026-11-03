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
        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->morphs('documentable');
            $table->string('type', 10)->default('item');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 1000);
            $table->text('details')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->string('tax_name', 100)->nullable();
            $table->string('hs_code', 20)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->nullableMorphs('source');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50)->nullable();
            $table->string('title')->nullable();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->date('valid_until')->nullable();
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
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('accepted_by_name')->nullable();
            $table->longText('accepted_signature')->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'number']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('document_lines');
    }
};
