<?php

namespace App\Jobs;

use App\Models\BellEquipment;
use App\Models\BellEquipmentDailyKpi;
use App\Models\Machine;
use App\Models\ProductionRecord;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellProductionRecordsJob
 *
 * Converts Bell Equipment OEM daily KPI data (loads_moved, payload_moved,
 * operating_hours, utilization_percent) into canonical ProductionRecord rows.
 *
 * This bridges the gap between Bell ISO15143-3 telemetry and the platform's
 * production reporting pipeline without requiring manual data entry.
 *
 * Run daily (after midnight) to back-fill the previous day's KPIs.
 * Upserts so re-running is safe.
 *
 * Queue: default (lightweight, non-critical timing)
 */
class SyncBellProductionRecordsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 120;

    /**
     * @param  int  $lookbackDays  Number of days to sync (default: 7 for catch-up)
     */
    public function __construct(private int $lookbackDays = 7)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $bellTeamId = (int) config('integrations.bell.team_id');

        if ($bellTeamId === 0) {
            Log::info('SyncBellProductionRecordsJob: BELL_TEAM_ID not configured, skipping.');

            return;
        }

        $start = Carbon::today()->subDays($this->lookbackDays)->startOfDay();
        $end = Carbon::yesterday()->endOfDay();

        // Load all Bell equipment that has a linked Machine record.
        $bellEquipments = BellEquipment::whereNotNull('machine_id')
            ->with('machine')
            ->get();

        if ($bellEquipments->isEmpty()) {
            Log::info('SyncBellProductionRecordsJob: no linked Bell machines found, skipping.');

            return;
        }

        $synced = 0;
        $skipped = 0;

        foreach ($bellEquipments as $bellEq) {
            $machine = $bellEq->machine;

            if (! $machine instanceof Machine) {
                continue;
            }

            // Load KPI rows for this machine over the lookback window.
            // Use whereDate() so the comparison works on both MySQL (DATE column)
            // and SQLite (stores date casts as 'Y-m-d H:i:s' strings).
            $kpiRows = BellEquipmentDailyKpi::where('equipment_key', $bellEq->equipment_key)
                ->whereDate('kpi_date', '>=', $start->toDateString())
                ->whereDate('kpi_date', '<=', $end->toDateString())
                ->orderBy('kpi_date')
                ->get();

            foreach ($kpiRows as $kpi) {
                // Skip days with zero production data (machine was off).
                if ((float) $kpi->loads_moved === 0.0 && (float) $kpi->payload_moved === 0.0) {
                    $skipped++;

                    continue;
                }

                $date = Carbon::parse($kpi->kpi_date)->toDateString();
                $shiftKey = 'oem_bell_'.$kpi->kpi_id;

                // Upsert based on (team_id, machine_id, record_date, shift='oem_auto').
                /** @var ProductionRecord|null $existing */
                $existing = ProductionRecord::withTrashed()
                    ->where('team_id', $machine->team_id)
                    ->where('machine_id', $machine->id)
                    ->whereDate('record_date', $date)
                    ->where('shift', 'oem_auto')
                    ->first();

                $data = [
                    'team_id' => $machine->team_id,
                    'machine_id' => $machine->id,
                    'mine_area_id' => $machine->mine_area_id,
                    'record_date' => $date,
                    'shift' => 'oem_auto',
                    'quantity_produced' => round((float) $kpi->payload_moved / 1000, 3), // kg → tonnes
                    'system_quantity' => round((float) $kpi->payload_moved / 1000, 3),
                    'unit' => 'tonnes',
                    'target_quantity' => 0,
                    'status' => 'completed',
                    'notes' => null,
                    'metadata' => [
                        'source' => 'bell_oem_kpi',
                        'bell_equipment_key' => $bellEq->equipment_key,
                        'bell_equipment_id' => $bellEq->equipment_id,
                        'loads_moved' => (int) $kpi->loads_moved,
                        'payload_moved_kg' => (float) $kpi->payload_moved,
                        'operating_hours' => (float) $kpi->operating_hours,
                        'idle_hours' => (float) $kpi->idle_hours,
                        'distance_km' => (float) $kpi->distance_travelled,
                        'fuel_used_litres' => (float) $kpi->fuel_used,
                        'utilization_percent' => (float) $kpi->utilization_percent,
                        'synced_at' => now()->toIso8601String(),
                    ],
                ];

                if ($existing !== null) {
                    // Restore soft-deleted rows and update.
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->fill($data)->save();
                } else {
                    ProductionRecord::create($data);
                }

                $synced++;
            }
        }

        Log::info('SyncBellProductionRecordsJob completed', [
            'lookback_days' => $this->lookbackDays,
            'synced' => $synced,
            'skipped' => $skipped,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellProductionRecordsJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
