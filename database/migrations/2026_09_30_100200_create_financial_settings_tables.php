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
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('rate', 6, 3)->default(0);
            $table->string('kind', 20)->default('standard');
            $table->string('authority', 20)->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('days')->default(0);
            $table->boolean('end_of_month')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->unsignedBigInteger('department_id')->default(0);
            $table->string('period', 20);
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['team_id', 'type', 'department_id', 'period']);
        });

        Schema::create('work_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_types');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('tax_rates');
    }
};
