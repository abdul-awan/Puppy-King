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
        // The taxable salary paid and the income tax deducted in a tax year before the payroll was used
        // (e.g. when a company starts in October, or by a previous employer), so the tax of the rest of
        // the year is right.
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('opening_tax_year')->nullable()->after('eobi_number');
            $table->decimal('opening_taxable', 15, 2)->nullable()->after('opening_tax_year');
            $table->decimal('opening_tax', 15, 2)->nullable()->after('opening_taxable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['opening_tax_year', 'opening_taxable', 'opening_tax']);
        });
    }
};
