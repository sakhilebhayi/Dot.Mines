<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Real bug found by actually running this platform's migrations for the
     * first time: MineArea::machines() (app/Models/MineArea.php) is a
     * hasMany(Machine::class) relationship, and
     * 2026_02_19_000010_make_machine_mine_area_id_not_nullable.php assumes
     * `machines.mine_area_id` already exists — but no migration in this
     * repository ever actually created that column. Any query that touches
     * the relationship (e.g. MineAreaManager's ->withCount(['machines', ...]))
     * failed with "no such column: machines.mine_area_id". This migration adds
     * the column that was always assumed to exist.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('machines', 'mine_area_id')) {
            Schema::table('machines', function (Blueprint $table) {
                $table->foreignId('mine_area_id')
                    ->nullable()
                    ->after('team_id')
                    ->constrained('mine_areas')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('machines', 'mine_area_id')) {
            Schema::table('machines', function (Blueprint $table) {
                $table->dropConstrainedForeignId('mine_area_id');
            });
        }
    }
};
