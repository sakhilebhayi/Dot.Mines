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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->string('title');
            $table->string('type'); // truck_sensors, tire_condition, load_cycle, fuel, engine_parts, maintenance, custom
            $table->string('status')->default('pending'); // pending, completed, failed

            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('format')->default('pdf'); // pdf, csv, xlsx

            $table->json('filters')->nullable();

            $table->foreignId('generated_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('team_id');
            $table->index('status');
            $table->index('type');
            $table->index('generated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
