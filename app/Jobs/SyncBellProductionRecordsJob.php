<?php

namespace App\Jobs;

use App\Models\BellEquipment;
use App\Models\BellEquipmentDailyKpi;
use App\Models\BellEquipmentLoadCountHistory;
use App\Models\Machine;
use App\Models\ProductionRecord;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellProductionRecordsJob
 *
 * Converts Bell OEM telemetry into canonical ProductionRecord rows via two paths:
 *
 * PATH 1 — bell_equipment_daily_kpis (authoritative, populated by ISO15143-3 snapshot):
 *   Covers historical days (yesterday and earlier). Uses pre-computed daily totals.
 *
 * PATH 2 — bell_equipment_load_count_history (intraday, populated every 5 minutes):
 *   Covers TODAY. Derives loads and payload from cumulative counter deltas so the
 *   Production page reflects current shift data without waiting until midnight.
 *   Skipped for any day that already has a daily KPI row (path 1 takes priority).
 *
 * Upserts are idempotent — re-running never duplicates records.
 *
 * Queue: default (lightweight, non-critical timing)
 */
class SyncBellProductionRecordsJob implements ShouldBeUnique, ShouldQueue
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

        // Load all Bell equipment that has a linked Machine record.
        $bellEquipments = BellEquipment::whereNotNull('machine_id')
            ->with('machine')
            ->get();

        if ($bellEquipments->isEmpty()) {
            Log::info('SyncBellProductionRecordsJob: no linked Bell machines found, skipping.');

            return;
        }

        $counters = ['kpi_synced' => 0, 'intraday_synced' => 0, 'skipped' => 0];

        // PATH 1: historical days from bell_equipment_daily_kpis (authoritative)
        $histStart = Carbon::today()->subDays($this->lookbackDays)->startOfDay();
        $histEnd = Carbon::yesterday()->endOfDay();

        if ($histStart->lessThanOrEqualTo($histEnd)) {
            $this->syncFromDailyKpis($bellEquipments, $histStart, $histEnd, $counters);
        }

        // PATH 2: today's intraday data from bell_equipment_load_count_history
        $this->syncIntradayFromLoadHistory($bellEquipments, today()->toDateString(), $counters);

        Log::info('SyncBellProductionRecordsJob completed', [
            'lookback_days' => $this->lookbackDays,
            'kpi_synced' => $counters['kpi_synced'],
            'intraday_synced' => $counters['intraday_synced'],
            'skipped' => $counters['skipped'],
        ]);
    }

    /**
     * PATH 1: sync production records from bell_equipment_daily_kpis (authoritative daily totals).
     *
     * @param  Collection<int, BellEquipment>  $bellEquipments
     * @param  array<string, int>  $counters
     */
    private function syncFromDailyKpis(Collection $bellEquipments, Carbon $start, Carbon $end, array &$counters): void
    {
        foreach ($bellEquipments as $bellEq) {
            $machine = $bellEq->machine;
            if (! $machine instanceof Machine) {
                continue;
            }

            $kpiRows = BellEquipmentDailyKpi::where('equipment_key', $bellEq->equipment_key)
                ->whereDate('kpi_date', '>=', $start->toDateString())
                ->whereDate('kpi_date', '<=', $end->toDateString())
                ->orderBy('kpi_date')
                ->get();

            foreach ($kpiRows as $kpi) {
                if ((float) $kpi->loads_moved === 0.0 && (float) $kpi->payload_moved === 0.0) {
                    $counters['skipped']++;

                    continue;
                }

                $date = Carbon::parse($kpi->kpi_date)->toDateString();
                $payloadKg = (float) $kpi->payload_moved;
                $tonnes = round($payloadKg / 1000, 3);
                $loadsMoved = (int) $kpi->loads_moved;

                $this->upsertProductionRecord($machine, $bellEq, $date, $loadsMoved, $tonnes, [
                    'source' => 'bell_oem_kpi',
                    'bell_equipment_key' => $bellEq->equipment_key,
                    'bell_equipment_id' => $bellEq->equipment_id,
                    'loads_moved' => $loadsMoved,
                    'payload_moved_kg' => $payloadKg,
                    'operating_hours' => (float) $kpi->operating_hours,
                    'idle_hours' => (float) $kpi->idle_hours,
                    'distance_km' => (float) $kpi->distance_travelled,
                    'fuel_used_litres' => (float) $kpi->fuel_used,
                    'utilization_percent' => (float) $kpi->utilization_percent,
                    'synced_at' => now()->toIso8601String(),
                ]);

                $counters['kpi_synced']++;
            }
        }
    }

    /**
     * PATH 2: derive today's production from cumulative load count history (intraday).
     *
     * Calculates delta = (latest cumulative reading today) − (last cumulative reading before today).
     * Skips machines that already have an authoritative daily KPI row for today.
     *
     * @param  Collection<int, BellEquipment>  $bellEquipments
     * @param  array<string, int>  $counters
     */
    private function syncIntradayFromLoadHistory(Collection $bellEquipments, string $date, array &$counters): void
    {
        $startOfDay = Carbon::parse($date)->startOfDay();

        foreach ($bellEquipments as $bellEq) {
            $machine = $bellEq->machine;
            if (! $machine instanceof Machine) {
                continue;
            }

            // Skip when authoritative daily KPI already covers today (path 1 wins).
            $hasKpi = BellEquipmentDailyKpi::where('equipment_key', $bellEq->equipment_key)
                ->whereDate('kpi_date', $date)
                ->exists();

            if ($hasKpi) {
                $counters['skipped']++;

                continue;
            }

            // Latest cumulative load count reading today.
            $todayLoad = BellEquipmentLoadCountHistory::where('equipment_key', $bellEq->equipment_key)
                ->whereDate('recorded_at', $date)
                ->whereNotNull('load_count')
                ->orderByDesc('recorded_at')
                ->first();

            if ($todayLoad === null) {
                Log::debug('SyncBellProductionRecordsJob: no intraday load history', [
                    'equipment_id' => $bellEq->equipment_id,
                    'date' => $date,
                ]);

                continue;
            }

            // Baseline: last cumulative reading before today (establish day-start counter).
            $baseline = BellEquipmentLoadCountHistory::where('equipment_key', $bellEq->equipment_key)
                ->where('recorded_at', '<', $startOfDay)
                ->whereNotNull('load_count')
                ->orderByDesc('recorded_at')
                ->first();

            $deltaLoads = $baseline !== null
                ? max(0, (int) $todayLoad->load_count - (int) $baseline->load_count)
                : (int) $todayLoad->load_count;

            // Payload delta (cumulative_payload is in the same table; may be a separate row).
            $todayPayload = BellEquipmentLoadCountHistory::where('equipment_key', $bellEq->equipment_key)
                ->whereDate('recorded_at', $date)
                ->whereNotNull('cumulative_payload')
                ->orderByDesc('recorded_at')
                ->first();

            $deltaPayloadKg = 0.0;
            $payloadUnits = 'kilogram';

            if ($todayPayload !== null) {
                $baselinePayload = BellEquipmentLoadCountHistory::where('equipment_key', $bellEq->equipment_key)
                    ->where('recorded_at', '<', $startOfDay)
                    ->whereNotNull('cumulative_payload')
                    ->orderByDesc('recorded_at')
                    ->first();

                $deltaPayloadKg = $baselinePayload !== null
                    ? max(0.0, (float) $todayPayload->cumulative_payload - (float) $baselinePayload->cumulative_payload)
                    : (float) $todayPayload->cumulative_payload;

                $payloadUnits = $todayPayload->payload_units ?? 'kilogram';
            }

            if ($deltaLoads === 0 && $deltaPayloadKg === 0.0) {
                $counters['skipped']++;

                continue;
            }

            $tonnes = strtolower($payloadUnits) === 'kilogram'
                ? round($deltaPayloadKg / 1000, 3)
                : round($deltaPayloadKg, 3);

            $this->upsertProductionRecord($machine, $bellEq, $date, $deltaLoads, $tonnes, [
                'source' => 'bell_load_history_intraday',
                'bell_equipment_key' => $bellEq->equipment_key,
                'bell_equipment_id' => $bellEq->equipment_id,
                'loads_moved' => $deltaLoads,
                'payload_delta_kg' => $deltaPayloadKg,
                'payload_units' => $payloadUnits,
                'baseline_load_count' => $baseline?->load_count,
                'latest_load_count' => $todayLoad->load_count,
                'synced_at' => now()->toIso8601String(),
            ]);

            $counters['intraday_synced']++;
        }
    }

    /**
     * Idempotent upsert of a production_record row keyed on (team_id, machine_id, record_date, shift='oem_auto').
     *
     * @param  array<string, mixed>  $metadata
     */
    private function upsertProductionRecord(
        Machine $machine,
        BellEquipment $bellEq,
        string $date,
        int $loadsMoved,
        float $tonnes,
        array $metadata,
    ): void {
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
            'quantity_produced' => $tonnes,
            'system_quantity' => $tonnes,
            'loads_moved' => $loadsMoved,
            'cycles_completed' => $loadsMoved, // Bell: one cycle = one load event
            'unit' => 'tonnes',
            'target_quantity' => 0,
            'status' => 'completed',
            'notes' => null,
            'metadata' => $metadata,
        ];

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->fill($data)->save();
        } else {
            ProductionRecord::create($data);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellProductionRecordsJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
