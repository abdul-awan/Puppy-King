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
        Schema::table('teams', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('is_personal');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('permissions');
            $table->unsignedSmallInteger('daily_capacity_minutes')->default(480)->after('hourly_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['permissions', 'hourly_rate', 'daily_capacity_minutes']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
