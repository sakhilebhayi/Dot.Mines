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

        if (! Schema::hasColumn('mine_areas', 'location')) {
            Schema::table('mine_areas', function (Blueprint $table) {
                $table->string('location')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mine_areas')) {
            return;
        }
        if (Schema::hasColumn('mine_areas', 'location')) {
            Schema::table('mine_areas', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }
    }
};
