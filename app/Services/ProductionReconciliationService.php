<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\Integration\TelemetryProductionCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reconciliation checks over the production pipeline (brief §18): the
 * machine-level, fleet-level and stored-record numbers must add up, and
 * when they don't the discrepancy is REPORTED with its size and likely
 * cause -- never silently hidden or "fixed" by inventing data.
 */
class ProductionReconciliationService
{
    /** Tonnes by which a record's payload delta may differ from its stored quantity. */
    private const TONNES_TOLERANCE = 0.1;

    /**
     * Live counters and the last production sync read the same counters at
     * different moments; a small load skew between them is timing, not
     * corruption. Beyond this many loads it is flagged.
     */
    private const LOAD_SKEW_TOLERANCE = 5;

    public function __construct(
        private TelemetryProductionCalculator $calculator,
        private OperationalSnapshotService $snapshots,
    ) {}

    /**
     * @return array{date: string, totals: array{machines: int, loads: int, tonnes: float}, checks: list<array{label: string, state: string, detail: string}>}
     */
    public function forDay(Team $team, ?string $date = null): array
    {
        $timezone = $team->timezone ?: config('app.timezone', 'UTC');
        $date ??= Carbon::now($timezone)->toDateString();
        $isToday = $date === Carbon::now($timezone)->toDateString();

        $records = ProductionRecord::where('team_id', $team->id)
            ->where('metadata->source', 'telemetry')
            ->whereDate('record_date', $date)
            ->get();

        $checks = [
            $this->recordInternalConsistency($records),
            $this->orphanedRecords($team->id, $records),
        ];

        if ($isToday) {
            $checks[] = $this->liveCountersVersusStoredRecords($team, $records);
        }

        return [
            'date' => $date,
            'totals' => [
                'machines' => $records->pluck('machine_id')->unique()->count(),
                'loads' => (int) $records->sum(fn (ProductionRecord $record): int => (int) data_get($record->metadata, 'loads', 0)),
                'tonnes' => round((float) $records->sum('quantity_produced'), 1),
            ],
            'checks' => $checks,
        ];
    }

    /**
     * Every telemetry record stores both the payload delta it was derived
     * from and the tonnes written to quantity_produced. If they disagree,
     * something rewrote one side without the other -- exactly the kind of
     * silent drift §18 wants surfaced.
     *
     * @param  Collection<int, ProductionRecord>  $records
     * @return array{label: string, state: string, detail: string}
     */
    private function recordInternalConsistency(Collection $records): array
    {
        $mismatched = $records->filter(function (ProductionRecord $record): bool {
            $delta = data_get($record->metadata, 'payload_delta');

            if (! is_numeric($delta)) {
                return false; // Load-only day: nothing to cross-check.
            }

            $expected = $this->calculator->payloadToTonnes(
                (float) $delta,
                is_string(data_get($record->metadata, 'payload_units')) ? data_get($record->metadata, 'payload_units') : null
            );

            return abs($expected - (float) $record->quantity_produced) > self::TONNES_TOLERANCE;
        });

        if ($mismatched->isEmpty()) {
            return [
                'label' => 'Record consistency',
                'state' => 'healthy',
                'detail' => $records->count().' telemetry record(s): stored tonnes match their payload deltas',
            ];
        }

        $worst = $mismatched->first();

        return [
            'label' => 'Record consistency',
            'state' => 'error',
            'detail' => $mismatched->count().' record(s) where stored tonnes disagree with the payload delta they were derived from (e.g. machine #'.$worst->machine_id.' on '.$worst->record_date?->toDateString().')',
        ];
    }

    /**
     * @param  Collection<int, ProductionRecord>  $records
     * @return array{label: string, state: string, detail: string}
     */
    private function orphanedRecords(int $teamId, Collection $records): array
    {
        $machineIds = $records->pluck('machine_id')->filter()->unique();
        $existing = Machine::where('team_id', $teamId)->whereIn('id', $machineIds)->pluck('id');
        $orphans = $machineIds->diff($existing);

        if ($orphans->isEmpty()) {
            return [
                'label' => 'Machine linkage',
                'state' => 'healthy',
                'detail' => 'Every production record maps to an existing machine',
            ];
        }

        return [
            'label' => 'Machine linkage',
            'state' => 'warning',
            'detail' => $orphans->count().' record(s) reference machines that no longer exist in this team (ids '.$orphans->implode(', ').')',
        ];
    }

    /**
     * Fleet "loads today" from the live counters versus the sum stored on
     * today's telemetry records. These read the same Bell counters at
     * different times, so a small skew is expected and said so; a large
     * one means a layer stopped updating.
     *
     * @param  Collection<int, ProductionRecord>  $records
     * @return array{label: string, state: string, detail: string}
     */
    private function liveCountersVersusStoredRecords(Team $team, Collection $records): array
    {
        $live = $this->snapshots->forTeam($team)
            ->filter(fn (array $snapshot): bool => $snapshot['loads_today'] !== null);

        if ($live->isEmpty()) {
            return [
                'label' => 'Live vs stored',
                'state' => 'warning',
                'detail' => 'No machine is reporting live counters today — live/stored comparison not possible',
            ];
        }

        $liveLoads = (int) $live->sum('loads_today');
        $storedLoads = (int) $records->sum(fn (ProductionRecord $record): int => (int) data_get($record->metadata, 'loads', 0));
        $skew = abs($liveLoads - $storedLoads);

        $detail = "live counters say {$liveLoads} loads today, stored records say {$storedLoads} (skew {$skew})";

        if ($skew > self::LOAD_SKEW_TOLERANCE) {
            return [
                'label' => 'Live vs stored',
                'state' => 'warning',
                'detail' => ucfirst($detail).' — beyond read-time skew; check whether the production deep sync is running',
            ];
        }

        return [
            'label' => 'Live vs stored',
            'state' => 'healthy',
            'detail' => ucfirst($detail).' — within read-time skew',
        ];
    }
}
