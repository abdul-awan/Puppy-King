<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Materials are used on a project, on a work order or on both. Changed first, so a
        // failure here leaves nothing half created.
        Schema::table('project_materials', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50)->nullable();
            $table->string('title');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->text('location')->nullable();
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status', 20)->default('open');
            $table->boolean('show_prices')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->longText('signature')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->text('customer_remarks')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable();
            $table->string('public_token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'number']);
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'date']);
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('ticket_id')->constrained()->nullOnDelete();
        });

        Schema::table('project_materials', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });

        Schema::dropIfExists('work_orders');

        // Materials that only belonged to a work order cannot stay without a project.
        DB::table('project_materials')->whereNull('project_id')->delete();

        Schema::table('project_materials', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });
    }
};
