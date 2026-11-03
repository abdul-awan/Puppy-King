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
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('planned_date')->nullable()->after('due_date');
            $table->unsignedInteger('planned_minutes')->nullable()->after('planned_date');

            $table->index(['team_id', 'planned_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'planned_date']);
            $table->dropColumn(['planned_date', 'planned_minutes']);
        });
    }
};
