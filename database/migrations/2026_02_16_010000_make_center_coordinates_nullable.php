<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // If using SQLite, perform a safe table-recreate to alter column nullability.
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Geofences
            if (Schema::hasTable('geofences')) {
                DB::beginTransaction();
                DB::statement('PRAGMA foreign_keys=off;');
                DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS geofences_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team_id INTEGER NOT NULL,
    mine_area_id INTEGER NULL,
    name TEXT NOT NULL,
    description TEXT NULL,
    type TEXT NOT NULL,
    coordinates TEXT NOT NULL,
    center_latitude REAL NULL,
    center_longitude REAL NULL,
    area_sqm REAL NULL,
    perimeter_m REAL NULL,
    status TEXT NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
SQL
                );
                // Copy data where possible
                DB::statement('INSERT INTO geofences_new (id, team_id, mine_area_id, name, description, type, coordinates, center_latitude, center_longitude, area_sqm, perimeter_m, status, notes, created_at, updated_at) SELECT id, team_id, mine_area_id, name, description, type, coordinates, center_latitude, center_longitude, area_sqm, perimeter_m, status, notes, created_at, updated_at FROM geofences;');
                DB::statement('DROP TABLE geofences;');
                DB::statement('ALTER TABLE geofences_new RENAME TO geofences;');
                // Recreate indexes
                DB::statement('CREATE INDEX IF NOT EXISTS idx_geofences_team_id ON geofences(team_id);');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_geofences_mine_area_id ON geofences(mine_area_id);');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_geofences_type ON geofences(type);');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_geofences_status ON geofences(status);');
                DB::statement('PRAGMA foreign_keys=on;');
                DB::commit();
            }

            // Mine areas
            if (Schema::hasTable('mine_areas')) {
                DB::beginTransaction();
                DB::statement('PRAGMA foreign_keys=off;');
                DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS mine_areas_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    team_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT NULL,
    location TEXT NULL,
    latitude REAL NULL,
    longitude REAL NULL,
    area_size_hectares REAL NULL,
    status TEXT NOT NULL DEFAULT 'active',
    manager_name TEXT NULL,
    manager_contact TEXT NULL,
    metadata TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    deleted_at TEXT NULL
);
SQL
                );
                DB::statement('INSERT INTO mine_areas_new (id, team_id, name, description, location, latitude, longitude, area_size_hectares, status, manager_name, manager_contact, metadata, created_at, updated_at, deleted_at) SELECT id, team_id, name, description, location, latitude, longitude, area_size_hectares, status, manager_name, manager_contact, metadata, created_at, updated_at, deleted_at FROM mine_areas;');
                DB::statement('DROP TABLE mine_areas;');
                DB::statement('ALTER TABLE mine_areas_new RENAME TO mine_areas;');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_mine_areas_team_id ON mine_areas(team_id);');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_mine_areas_status ON mine_areas(status);');
                DB::statement('PRAGMA foreign_keys=on;');
                DB::commit();
            }

            return;
        }

        // For non-sqlite drivers, use change() which relies on doctrine/dbal
        $tables = ['geofences', 'mine_areas'];

        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }

            if (! Schema::hasColumn($tbl, 'center_latitude') && ! Schema::hasColumn($tbl, 'center_longitude')) {
                continue;
            }

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (Schema::hasColumn($tbl, 'center_latitude')) {
                    $table->float('center_latitude')->nullable()->change();
                }
                if (Schema::hasColumn($tbl, 'center_longitude')) {
                    $table->float('center_longitude')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // For sqlite, reversing is risky; skip to avoid data loss.
            return;
        }

        $tables = ['geofences', 'mine_areas'];

        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (Schema::hasColumn($tbl, 'center_latitude')) {
                    $table->float('center_latitude')->nullable(false)->change();
                }
                if (Schema::hasColumn($tbl, 'center_longitude')) {
                    $table->float('center_longitude')->nullable(false)->change();
                }
            });
        }
    }
};
