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
        Schema::table('machines', function (Blueprint $table) {
            $table->foreignId('mine_area_id')
                ->nullable()
                ->after('integration_id')
                ->constrained('mine_areas')
                ->nullOnDelete();

            $table->index('mine_area_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropForeign(['mine_area_id']);
            $table->dropIndex(['mine_area_id']);
            $table->dropColumn('mine_area_id');
        });
    }
};
