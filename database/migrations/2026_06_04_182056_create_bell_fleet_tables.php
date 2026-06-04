<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates all tables for the Bell ISO15143-3 fleet API integration:
     *  - bell_equipment             : Master equipment / machine records
     *  - bell_equipment_current_status : Latest telemetry snapshot per machine
     *  - bell_equipment_telemetry_history : Full history of every API snapshot
     *  - bell_fleet_snapshots       : Raw JSON + metadata per API call
     *  - bell_integration_audit_logs : Execution audit trail
     *  - bell_equipment_daily_kpis  : Derived daily production KPIs
     *
     * Also creates two Power BI reporting views:
     *  - vw_bell_fleet_current_status
     *  - vw_bell_equipment_daily_kpis
     */
    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // Equipment master                                                    //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment', function (Blueprint $table) {
            $table->id('equipment_key');

            $table->string('oem_name', 50)->nullable();
            $table->string('model', 50)->nullable();

            $table->string('equipment_id', 100)->unique();
            $table->string('serial_number', 100)->nullable();
            $table->string('pin', 100)->nullable();

            $table->dateTime('unit_install_date_time')->nullable();

            $table->timestamps();
        });

        // ------------------------------------------------------------------ //
        // Current equipment status (one row per machine, replaced on sync)   //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_current_status', function (Blueprint $table) {
            $table->id('status_id');

            $table->unsignedBigInteger('equipment_key');

            $table->dateTime('snapshot_time')->nullable();

            $table->decimal('latitude', 18, 8)->nullable();
            $table->decimal('longitude', 18, 8)->nullable();

            $table->decimal('idle_hours', 18, 2)->nullable();
            $table->unsignedBigInteger('load_count')->nullable();
            $table->decimal('operating_hours', 18, 2)->nullable();

            $table->decimal('payload', 18, 2)->nullable();
            $table->string('payload_units', 20)->nullable();

            $table->decimal('def_percent', 18, 2)->nullable();

            $table->decimal('odometer', 18, 2)->nullable();
            $table->string('odometer_units', 20)->nullable();

            $table->decimal('fuel_consumed', 18, 2)->nullable();
            $table->string('fuel_units', 20)->nullable();

            $table->decimal('fuel_remaining_percent', 18, 2)->nullable();

            $table->boolean('engine_running')->nullable();
            $table->string('engine_number', 100)->nullable();

            $table->dateTime('last_telemetry_date')->nullable();
            $table->dateTime('updated_date')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->cascadeOnDelete();
            $table->index('equipment_key');
        });

        // ------------------------------------------------------------------ //
        // Historical telemetry (append-only)                                 //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_telemetry_history', function (Blueprint $table) {
            $table->id('history_id');

            $table->unsignedBigInteger('equipment_key');

            $table->dateTime('snapshot_time')->nullable();

            $table->decimal('latitude', 18, 8)->nullable();
            $table->decimal('longitude', 18, 8)->nullable();

            $table->decimal('idle_hours', 18, 2)->nullable();
            $table->unsignedBigInteger('load_count')->nullable();
            $table->decimal('operating_hours', 18, 2)->nullable();

            $table->decimal('payload', 18, 2)->nullable();
            $table->string('payload_units', 20)->nullable();

            $table->decimal('def_percent', 18, 2)->nullable();

            $table->decimal('odometer', 18, 2)->nullable();
            $table->string('odometer_units', 20)->nullable();

            $table->decimal('fuel_consumed', 18, 2)->nullable();
            $table->string('fuel_units', 20)->nullable();

            $table->decimal('fuel_remaining_percent', 18, 2)->nullable();

            $table->boolean('engine_running')->nullable();
            $table->string('engine_number', 100)->nullable();

            $table->dateTime('telemetry_date')->nullable();
            $table->dateTime('created_date')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->cascadeOnDelete();
            $table->index('equipment_key');
            $table->index('telemetry_date');
        });

        // ------------------------------------------------------------------ //
        // Fleet snapshot – one row per API call                              //
        // ------------------------------------------------------------------ //
        Schema::create('bell_fleet_snapshots', function (Blueprint $table) {
            $table->id('snapshot_id');

            $table->dateTime('snapshot_time')->nullable();
            $table->string('fleet_version', 10)->nullable();
            $table->unsignedInteger('equipment_count')->default(0);

            $table->longText('raw_json')->nullable();

            $table->dateTime('created_date')->useCurrent();

            $table->index('snapshot_time');
        });

        // ------------------------------------------------------------------ //
        // Integration audit log                                               //
        // ------------------------------------------------------------------ //
        Schema::create('bell_integration_audit_logs', function (Blueprint $table) {
            $table->id('log_id');

            $table->dateTime('execution_date')->nullable();
            $table->boolean('success')->default(false);

            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_inserted')->default(0);
            $table->unsignedInteger('records_updated')->default(0);

            $table->longText('error_message')->nullable();

            $table->index('execution_date');
        });

        // ------------------------------------------------------------------ //
        // Daily KPI table (derived / calculated)                             //
        // ------------------------------------------------------------------ //
        Schema::create('bell_equipment_daily_kpis', function (Blueprint $table) {
            $table->id('kpi_id');

            $table->unsignedBigInteger('equipment_key');
            $table->date('kpi_date');

            $table->unsignedBigInteger('loads_moved')->default(0);
            $table->decimal('payload_moved', 18, 2)->default(0);
            $table->decimal('operating_hours', 18, 2)->default(0);
            $table->decimal('idle_hours', 18, 2)->default(0);
            $table->decimal('distance_travelled', 18, 2)->default(0);
            $table->decimal('fuel_used', 18, 2)->default(0);
            $table->decimal('utilization_percent', 18, 2)->default(0);

            $table->dateTime('created_date')->useCurrent();

            $table->foreign('equipment_key')->references('equipment_key')->on('bell_equipment')->cascadeOnDelete();
            $table->index('equipment_key');
            $table->index('kpi_date');
            $table->unique(['equipment_key', 'kpi_date']);
        });

        // ------------------------------------------------------------------ //
        // Power BI view: current fleet status                                //
        // ------------------------------------------------------------------ //
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

        // ------------------------------------------------------------------ //
        // Power BI view: daily KPIs                                          //
        // ------------------------------------------------------------------ //
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_bell_equipment_daily_kpis');
        DB::statement('DROP VIEW IF EXISTS vw_bell_fleet_current_status');

        Schema::dropIfExists('bell_equipment_daily_kpis');
        Schema::dropIfExists('bell_integration_audit_logs');
        Schema::dropIfExists('bell_fleet_snapshots');
        Schema::dropIfExists('bell_equipment_telemetry_history');
        Schema::dropIfExists('bell_equipment_current_status');
        Schema::dropIfExists('bell_equipment');
    }
};
