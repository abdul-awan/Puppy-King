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
        Schema::create('daily_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Day-to-day spending (tea, fuel, stationery…), usually from the cash box.
        Schema::create('daily_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50)->nullable();
            $table->date('date');
            $table->foreignId('category_id')->nullable()->constrained('daily_expense_categories')->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 20)->default('cash');
            $table->string('paid_by', 100)->nullable();
            $table->string('paid_to', 150)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'number']);
            $table->index(['team_id', 'date']);
        });

        // Cash put into or taken out of the cash box (petty cash), other than expenses.
        Schema::create('petty_cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('direction', 10)->default('in');
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->string('person', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_entries');
        Schema::dropIfExists('daily_expenses');
        Schema::dropIfExists('daily_expense_categories');
    }
};
