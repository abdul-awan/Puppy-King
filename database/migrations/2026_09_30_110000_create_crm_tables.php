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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 20)->default('customer');
            $table->string('business_type', 30)->nullable();
            $table->string('ntn', 20)->nullable();
            $table->string('strn', 20)->nullable();
            $table->string('cnic', 15)->nullable();
            $table->boolean('sales_tax_registered')->default(false);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('industry', 100)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->nullable();
            $table->string('language', 5)->default('en');
            $table->string('iban', 34)->nullable();
            $table->text('remarks')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'name']);
            $table->index(['team_id', 'relationship']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('salutation', 20)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('cnic', 15)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('language', 5)->default('en');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('marketing_consent')->default(false);
            $table->text('remarks')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'last_name']);
        });

        Schema::create('company_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('position', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_decision_maker')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_contact');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
