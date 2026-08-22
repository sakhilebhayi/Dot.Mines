<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('mine_area_id')->nullable()->constrained('mine_areas')->onDelete('cascade');
            $table->string('name');
            $table->enum('sensor_type', ['temperature', 'humidity', 'dust', 'vibration', 'noise', 'air_quality', 'pressure', 'custom', 'accelerometer']);
            $table->string('device_id')->unique();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'online', 'offline', 'error'])->default('active');
            $table->json('last_reading')->nullable();
            $table->timestamp('last_reading_at')->nullable();
            $table->decimal('location_latitude', 10, 8)->nullable();
            $table->decimal('location_longitude', 11, 8)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('mine_area_id');
            $table->index('device_id');
            $table->index('status');
        });

        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iot_sensor_id')->constrained('iot_sensors')->onDelete('cascade');
            $table->enum('sensor_type', ['temperature', 'humidity', 'dust', 'vibration', 'noise', 'air_quality', 'pressure', 'custom']);
            $table->decimal('value', 10, 4);
            $table->string('unit');
            $table->timestamp('timestamp');
            $table->float('quality_score')->default(1.0);
            $table->timestamps();

            $table->index('iot_sensor_id');
            $table->index('timestamp');
        });

        Schema::create('production_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mine_area_id')->constrained('mine_areas')->onDelete('cascade');
            $table->date('forecast_date');
            $table->string('material_name');
            $table->decimal('predicted_tonnage', 10, 2);
            $table->float('confidence_score')->default(0.0);
            $table->string('model_version')->default('1.0');
            $table->json('factors')->nullable();
            $table->timestamps();

            $table->index('mine_area_id');
            $table->index('forecast_date');
            // Explicit name: the auto-generated one is 68 chars and MySQL caps
            // identifiers at 64 (surfaced by the engine-copy rehearsal).
            $table->unique(['mine_area_id', 'forecast_date', 'material_name'], 'pf_area_date_material_unique');
        });

        Schema::create('compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mine_area_id')->constrained('mine_areas')->onDelete('cascade');
            $table->enum('report_type', ['environmental', 'safety', 'production', 'equipment', 'custom']);
            $table->foreignId('generated_by')->constrained('users')->onDelete('set null')->nullable();
            $table->date('report_date');
            $table->enum('status', ['draft', 'pending_review', 'approved', 'archived'])->default('draft');
            $table->json('data')->nullable();
            $table->string('file_path')->nullable();
            $table->float('compliance_score')->nullable();
            $table->json('issues')->nullable();
            $table->timestamps();

            $table->index('mine_area_id');
            $table->index('report_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_reports');
        Schema::dropIfExists('production_forecasts');
        Schema::dropIfExists('sensor_readings');
        Schema::dropIfExists('iot_sensors');
    }
};
