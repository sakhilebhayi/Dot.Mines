<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_records', function (Blueprint $table) {
            $table->unsignedBigInteger('loads_moved')
                ->nullable()
                ->after('system_quantity')
                ->comment('Number of loads from Bell CumulativeLoadCount delta (or manual entry)');
            $table->unsignedBigInteger('cycles_completed')
                ->nullable()
                ->after('loads_moved')
                ->comment('Completed haul cycles; equals loads_moved for Bell equipment');
        });
    }

    public function down(): void
    {
        Schema::table('production_records', function (Blueprint $table) {
            $table->dropColumn(['loads_moved', 'cycles_completed']);
        });
    }
};
