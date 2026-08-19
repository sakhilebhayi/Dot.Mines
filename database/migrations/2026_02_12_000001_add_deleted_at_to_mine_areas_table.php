<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mine_areas', function (Blueprint $table) {
            // Only add deleted_at if it doesn't already exist
            if (! Schema::hasColumn('mine_areas', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mine_areas', function (Blueprint $table) {
            if (Schema::hasColumn('mine_areas', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
