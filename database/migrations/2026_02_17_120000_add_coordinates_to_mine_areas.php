<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mine_areas')) {
            return;
        }

        Schema::table('mine_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('mine_areas', 'coordinates')) {
                // JSON column for storing legacy/geometry coordinates
                $table->json('coordinates')->nullable()->after('team_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mine_areas')) {
            return;
        }

        Schema::table('mine_areas', function (Blueprint $table) {
            if (Schema::hasColumn('mine_areas', 'coordinates')) {
                $table->dropColumn('coordinates');
            }
        });
    }
};
