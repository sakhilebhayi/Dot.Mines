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
            if (! Schema::hasColumn('mine_areas', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('location');
            }
            if (! Schema::hasColumn('mine_areas', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('mine_areas', 'area_size_hectares')) {
                $table->decimal('area_size_hectares', 10, 2)->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('mine_areas', 'manager_name')) {
                $table->string('manager_name')->nullable()->after('status');
            }
            if (! Schema::hasColumn('mine_areas', 'manager_contact')) {
                $table->string('manager_contact')->nullable()->after('manager_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mine_areas')) {
            return;
        }

        Schema::table('mine_areas', function (Blueprint $table) {
            if (Schema::hasColumn('mine_areas', 'manager_contact')) {
                $table->dropColumn('manager_contact');
            }
            if (Schema::hasColumn('mine_areas', 'manager_name')) {
                $table->dropColumn('manager_name');
            }
            if (Schema::hasColumn('mine_areas', 'area_size_hectares')) {
                $table->dropColumn('area_size_hectares');
            }
            if (Schema::hasColumn('mine_areas', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('mine_areas', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
