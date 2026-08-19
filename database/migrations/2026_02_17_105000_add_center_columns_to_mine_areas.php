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
            if (! Schema::hasColumn('mine_areas', 'center_latitude')) {
                $table->decimal('center_latitude', 10, 8)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('mine_areas', 'center_longitude')) {
                $table->decimal('center_longitude', 11, 8)->nullable()->after('center_latitude');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mine_areas')) {
            return;
        }

        Schema::table('mine_areas', function (Blueprint $table) {
            if (Schema::hasColumn('mine_areas', 'center_longitude')) {
                $table->dropColumn('center_longitude');
            }
            if (Schema::hasColumn('mine_areas', 'center_latitude')) {
                $table->dropColumn('center_latitude');
            }
        });
    }
};
