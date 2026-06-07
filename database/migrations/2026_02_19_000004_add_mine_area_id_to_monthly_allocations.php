<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_monthly_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('fuel_monthly_allocations', 'mine_area_id')) {
                $table->foreignId('mine_area_id')->nullable()->after('team_id')->constrained('mine_areas')->nullOnDelete();
            }

            // Drop existing team/year/month unique and replace with team/mine_area/year/month
            try {
                $table->dropUnique(['team_id', 'year', 'month']);
            } catch (Exception $e) {
                // Some drivers (sqlite) may not support dropUnique by columns; ignore if it fails
            }

            $table->unique(['team_id', 'mine_area_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('fuel_monthly_allocations', function (Blueprint $table) {
            try {
                $table->dropUnique(['team_id', 'mine_area_id', 'year', 'month']);
            } catch (Exception $e) {
                // ignore
            }

            $table->unique(['team_id', 'year', 'month']);

            if (Schema::hasColumn('fuel_monthly_allocations', 'mine_area_id')) {
                $table->dropConstrainedForeignId('mine_area_id');
            }
        });
    }
};
