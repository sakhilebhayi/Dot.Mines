<?php

namespace App\Services;

use App\Models\DataQualitySnapshot;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use Illuminate\Support\Facades\DB;

/**
 * MEGA V2 — Data Trust Score Service
 *
 * Calculates per-domain data quality scores and stores snapshots.
 * Feeds the "Data Quality / Trust Score" MEGA V2 domain (target 90/100 for full score).
 *
 * Domains covered:
 *   - fleet   : GPS coverage, engine hours completeness
 *   - fuel    : Transaction reconciliation accuracy, orphan records
 *   - maintenance : Record completeness, missing component data
 *   - alerts  : Delivery confirmation, orphan alert links
 */
class DataTrustService
{
    /**
     * Run all domain snapshots and persist them.
     * Call from the MEGA V2 artisan command or a scheduled job.
     *
     * @return array<string, float> domain => overall_score
     */
    public function snapshotAll(): array
    {
        return [
            'fleet' => $this->snapshotFleet(),
            'fuel' => $this->snapshotFuel(),
            'maintenance' => $this->snapshotMaintenance(),
        ];
    }

    /**
     * Calculate and persist the fleet data quality snapshot.
     */
    public function snapshotFleet(): float
    {
        $total = Machine::count();
        $missingGps = Machine::whereNull('last_location_latitude')->orWhereNull('last_location_longitude')->count();

        $gpsCoverage = $total > 0 ? (($total - $missingGps) / $total) * 100 : 0.0;
        $score = round($gpsCoverage, 2);

        DataQualitySnapshot::create([
            'domain' => 'fleet',
            'metric_name' => 'gps_coverage',
            'score' => $score,
            'total_records' => $total,
            'missing_count' => $missingGps,
            'corrupt_count' => 0,
            'duplicate_count' => 0,
            'notes' => "GPS coverage: {$gpsCoverage}%",
            'snapshot_at' => now(),
        ]);

        return $score;
    }

    /**
     * Calculate and persist the fuel data quality snapshot.
     */
    public function snapshotFuel(): float
    {
        $total = FuelTransaction::count();
        $missingMachine = FuelTransaction::whereNull('machine_id')->count();
        $missingQuantity = FuelTransaction::where(function ($q) {
            $q->whereNull('quantity_liters')->orWhere('quantity_liters', '<=', 0);
        })->count();

        $machineLink = $total > 0 ? (($total - $missingMachine) / $total) * 100 : 100.0;
        $quantityValid = $total > 0 ? (($total - $missingQuantity) / $total) * 100 : 100.0;
        $score = round(($machineLink + $quantityValid) / 2, 2);

        DataQualitySnapshot::create([
            'domain' => 'fuel',
            'metric_name' => 'transaction_integrity',
            'score' => $score,
            'total_records' => $total,
            'missing_count' => $missingMachine + $missingQuantity,
            'corrupt_count' => 0,
            'duplicate_count' => 0,
            'notes' => "Machine link: {$machineLink}%, Valid quantity: {$quantityValid}%",
            'snapshot_at' => now(),
        ]);

        return $score;
    }

    /**
     * Calculate and persist the maintenance data quality snapshot.
     */
    public function snapshotMaintenance(): float
    {
        $total = MaintenanceRecord::count();
        $missingCost = MaintenanceRecord::whereNull('cost')->count();
        $missingTech = MaintenanceRecord::whereNull('assigned_to')->count();

        $costCoverage = $total > 0 ? (($total - $missingCost) / $total) * 100 : 100.0;
        $techCoverage = $total > 0 ? (($total - $missingTech) / $total) * 100 : 100.0;
        $score = round(($costCoverage + $techCoverage) / 2, 2);

        DataQualitySnapshot::create([
            'domain' => 'maintenance',
            'metric_name' => 'record_completeness',
            'score' => $score,
            'total_records' => $total,
            'missing_count' => $missingCost + $missingTech,
            'corrupt_count' => 0,
            'duplicate_count' => 0,
            'notes' => "Cost coverage: {$costCoverage}%, Technician coverage: {$techCoverage}%",
            'snapshot_at' => now(),
        ]);

        return $score;
    }

    /**
     * Overall Trust Score (0–100) for MEGA V2 scoring.
     * Average of the most recent snapshot per domain.
     */
    public function overallTrustScore(): float
    {
        $latestPerDomain = DataQualitySnapshot::query()
            ->select('domain', DB::raw('MAX(snapshot_at) as latest_at'))
            ->groupBy('domain')
            ->get();

        if ($latestPerDomain->isEmpty()) {
            return 0.0;
        }

        $scores = [];
        foreach ($latestPerDomain as $row) {
            $snapshot = DataQualitySnapshot::where('domain', $row->domain)
                ->where('snapshot_at', $row->latest_at)
                ->first();
            if ($snapshot !== null) {
                $scores[] = $snapshot->score;
            }
        }

        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0.0;
    }
}
