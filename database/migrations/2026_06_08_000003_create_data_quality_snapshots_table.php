<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEGA V2 — Data Trust Score Infrastructure
 *
 * Periodic snapshots of data quality metrics per domain.
 * Feeds the "Data Quality / Trust Score" MEGA V2 domain (+3 points).
 * Also satisfies ISO 27001 data integrity control A.8.9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 80)->index();        // e.g. fleet, fuel, maintenance, alerts
            $table->string('metric_name', 120);           // e.g. gps_completeness, fuel_reconciliation
            $table->decimal('score', 5, 2);               // 0.00 – 100.00 (higher = better)
            $table->unsignedInteger('total_records');
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('corrupt_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->decimal('reconciliation_accuracy', 5, 2)->nullable(); // matches expected vs actual
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('snapshot_at')->index();
            $table->timestamps();

            $table->index(['domain', 'metric_name', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_snapshots');
    }
};
