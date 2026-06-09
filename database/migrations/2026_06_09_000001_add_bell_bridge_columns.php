<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bell–Machine Bridge Migration
 *
 * Adds:
 *   - machines.external_id         — Bell EquipmentID (or other OEM external ID)
 *   - machines.last_seen_at        — timestamp of last OEM telemetry sync
 *   - machines.operating_hours     — cumulative operating hours from OEM
 *   - machines.total_distance_km   — odometer in km (derived from OEM odometer)
 *   - machines.odometer            — raw OEM odometer value (OEM units)
 *   - bell_equipment.machine_id    — FK to machines.id (nullable – linked after match)
 *   - bell_equipment.machine_matched_at — when the match was confirmed
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── machines: OEM telemetry columns ───────────────────────────────── //
        Schema::table('machines', function (Blueprint $table) {
            if (! Schema::hasColumn('machines', 'external_id')) {
                $table->string('external_id')->nullable()->after('manufacturer_id')
                    ->comment('External OEM equipment ID (e.g. Bell EquipmentID)');
            }

            if (! Schema::hasColumn('machines', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_location_update')
                    ->comment('Timestamp of last received OEM telemetry snapshot');
            }

            if (! Schema::hasColumn('machines', 'operating_hours')) {
                $table->decimal('operating_hours', 10, 2)->nullable()->after('hours_meter')
                    ->comment('Cumulative engine operating hours from OEM telemetry');
            }

            if (! Schema::hasColumn('machines', 'total_distance_km')) {
                $table->decimal('total_distance_km', 12, 2)->nullable()->after('operating_hours')
                    ->comment('Total distance in kilometres (converted from OEM odometer)');
            }

            if (! Schema::hasColumn('machines', 'odometer')) {
                $table->decimal('odometer', 14, 2)->nullable()->after('total_distance_km')
                    ->comment('Raw OEM odometer reading (units depend on OEM)');
            }
        });

        // Add indexes only if they don't exist yet
        $machineIndexes = collect(DB::select("PRAGMA index_list('machines')"))->pluck('name');
        if (! $machineIndexes->contains('machines_external_id_index')) {
            Schema::table('machines', fn (Blueprint $t) => $t->index('external_id'));
        }
        if (! $machineIndexes->contains('machines_last_seen_at_index')) {
            Schema::table('machines', fn (Blueprint $t) => $t->index('last_seen_at'));
        }

        // ── bell_equipment: link back to machines ─────────────────────────── //
        // SQLite invalidates views when altering tables; drop both views and recreate.
        DB::statement('DROP VIEW IF EXISTS vw_bell_fleet_current_status');
        DB::statement('DROP VIEW IF EXISTS vw_bell_equipment_daily_kpis');

        Schema::table('bell_equipment', function (Blueprint $table) {
            if (! Schema::hasColumn('bell_equipment', 'machine_id')) {
                $table->unsignedBigInteger('machine_id')->nullable()->after('equipment_key')
                    ->comment('FK to machines.id – populated after serial_number match');

                $table->foreign('machine_id')
                    ->references('id')
                    ->on('machines')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bell_equipment', 'machine_matched_at')) {
                $table->timestamp('machine_matched_at')->nullable()->after('machine_id')
                    ->comment('When the BellEquipment was matched to a Machine record');
            }
        });

        $bellIndexes = collect(DB::select("PRAGMA index_list('bell_equipment')"))->pluck('name');
        if (! $bellIndexes->contains('bell_equipment_machine_id_index')) {
            Schema::table('bell_equipment', fn (Blueprint $t) => $t->index('machine_id'));
        }

        DB::statement('
            CREATE VIEW vw_bell_fleet_current_status AS
            SELECT
                e.equipment_id,
                e.oem_name,
                e.model,
                s.engine_running,
                s.fuel_remaining_percent,
                s.odometer,
                s.operating_hours,
                s.idle_hours,
                s.load_count,
                s.payload,
                s.latitude,
                s.longitude,
                s.last_telemetry_date,
                s.updated_date
            FROM bell_equipment_current_status s
            INNER JOIN bell_equipment e ON e.equipment_key = s.equipment_key
        ');

        DB::statement('
            CREATE VIEW vw_bell_equipment_daily_kpis AS
            SELECT
                e.equipment_id,
                e.oem_name,
                e.model,
                k.kpi_date,
                k.loads_moved,
                k.payload_moved,
                k.fuel_used,
                k.utilization_percent,
                k.distance_travelled,
                k.operating_hours,
                k.idle_hours
            FROM bell_equipment_daily_kpis k
            INNER JOIN bell_equipment e ON e.equipment_key = k.equipment_key
        ');
    }

    public function down(): void
    {
        Schema::table('bell_equipment', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropIndex(['machine_id']);
            $table->dropColumn(['machine_id', 'machine_matched_at']);
        });

        Schema::table('machines', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn(['external_id', 'last_seen_at', 'operating_hours', 'total_distance_km', 'odometer']);
        });
    }
};
