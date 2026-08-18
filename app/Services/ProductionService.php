<?php

namespace App\Services;

use App\Models\ProductionForecast;
use App\Models\ProductionRecord;
use App\Models\ProductionTarget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;

class ProductionService
{
    /**
     * @return Paginator<ProductionRecord>
     */
    public function getProductionByTeam(int $teamId, ?Carbon $startDate = null, ?Carbon $endDate = null)
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        return ProductionRecord::forTeam($teamId)
            ->betweenDates($startDate, $endDate)
            ->orderByDesc('record_date')
            ->paginate(15);
    }

    /**
     * @return Collection<int,ProductionRecord>
     */
    public function getTodayProduction(int $teamId): Collection
    {
        return ProductionRecord::forTeam($teamId)
            ->where('record_date', Carbon::today())
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function getProductionStatistics(int $teamId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        $records = ProductionRecord::forTeam($teamId)
            ->betweenDates($startDate, $endDate)
            ->get();

        $totalProduced = $records->sum('quantity_produced');
        $totalTarget = $records->sum('target_quantity');
        $recordCount = $records->count();
        $avgProduction = $recordCount > 0 ? $totalProduced / $recordCount : 0;
        $completedCount = $records->where('status', 'completed')->count();

        return [
            'total_produced' => $totalProduced,
            'total_target' => $totalTarget,
            'total_loads' => (int) $records->sum(fn (ProductionRecord $record) => $this->recordLoads($record)),
            'total_cycles' => (int) $records->sum(fn (ProductionRecord $record) => $this->recordCycles($record)),
            'achievement_rate' => $totalTarget > 0 ? ($totalProduced / $totalTarget) * 100 : 0,
            'average_daily_production' => $avgProduction,
            'total_records' => $recordCount,
            'completed_records' => $completedCount,
            'pending_records' => $recordCount - $completedCount,
            'above_target_count' => $records->where('is_above_target', true)->count(),
            'below_target_count' => $records->where('is_above_target', false)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProductionRecord(int $teamId, array $data): ProductionRecord
    {
        return ProductionRecord::create([
            'team_id' => $teamId,
            'mine_area_id' => $data['mine_area_id'] ?? null,
            'machine_id' => $data['machine_id'] ?? null,
            'record_date' => $data['record_date'],
            'shift' => $data['shift'] ?? 'day',
            'quantity_produced' => $data['quantity_produced'],
            'unit' => $data['unit'] ?? 'tonnes',
            'target_quantity' => $data['target_quantity'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'completed',
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProductionRecord(ProductionRecord $record, array $data): ProductionRecord
    {
        $record->update($data);

        return $record;
    }

    public function deleteProductionRecord(ProductionRecord $record): ?bool
    {
        return $record->delete();
    }

    /**
     * @return Collection<int,ProductionTarget>
     */
    public function getActiveTargets(int $teamId): Collection
    {
        return ProductionTarget::forTeam($teamId)
            ->active()
            ->where('end_date', '>=', Carbon::today())
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTarget(int $teamId, array $data): ProductionTarget
    {
        return ProductionTarget::create([
            'team_id' => $teamId,
            'mine_area_id' => $data['mine_area_id'] ?? null,
            'period_type' => $data['period_type'] ?? 'daily',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'target_quantity' => $data['target_quantity'],
            'unit' => $data['unit'] ?? 'tonnes',
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<string, array<string, mixed>>
     */
    public function getProductionTrend(int $teamId, int $days = 30): \Illuminate\Support\Collection
    {
        $records = ProductionRecord::forTeam($teamId)
            ->where('record_date', '>=', Carbon::now()->subDays($days))
            ->orderBy('record_date')
            ->get()
            ->groupBy('record_date');

        return $records->map(function ($dayRecords) {
            return [
                'date' => $dayRecords->first()->record_date->format('Y-m-d'),
                'produced' => $dayRecords->sum('quantity_produced'),
                'target' => $dayRecords->sum('target_quantity'),
                'count' => $dayRecords->count(),
                'loads' => (int) $dayRecords->sum(fn (ProductionRecord $record) => $this->recordLoads($record)),
            ];
        });
    }

    /**
     * Real loads for a record: telemetry-derived records aggregate a whole
     * day of loads into one row and carry the true count in metadata;
     * a manual entry without one counts as the single load it always did.
     */
    public function recordLoads(ProductionRecord $record): int
    {
        $loads = data_get($record->metadata, 'loads');

        return is_numeric($loads) ? (int) $loads : 1;
    }

    /**
     * Real cycles for a record, falling back to the dashboard's historical
     * proxy (one cycle per completed manual record).
     */
    public function recordCycles(ProductionRecord $record): int
    {
        $cycles = data_get($record->metadata, 'cycles');

        if (is_numeric($cycles)) {
            return (int) $cycles;
        }

        return $record->status === 'completed' ? 1 : 0;
    }

    /**
     * @return \Illuminate\Support\Collection<string, array<string, mixed>>
     */
    public function getProductionByMineArea(int $teamId): \Illuminate\Support\Collection
    {
        $records = ProductionRecord::forTeam($teamId)
            ->where('record_date', '>=', Carbon::now()->subDays(30))
            ->with('mineArea')
            ->get();

        return $records->groupBy('mine_area_id')->map(function ($areaRecords) {
            $area = $areaRecords->first()?->mineArea;

            return [
                'mine_area_id' => $area?->id,
                'mine_area_name' => $area?->name ?? 'Unknown',
                'total_produced' => $areaRecords->sum('quantity_produced'),
                'total_target' => $areaRecords->sum('target_quantity'),
                'record_count' => $areaRecords->count(),
            ];
        });
    }

    /**
     * Production per machine over the trailing 30 days -- mirrors
     * getProductionByMineArea() but grouped by machine instead of area.
     * Real per-machine breakdown was entirely missing: getProductionStatistics()
     * only ever aggregated at the team level.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getProductionByMachine(int $teamId): \Illuminate\Support\Collection
    {
        $records = ProductionRecord::forTeam($teamId)
            ->whereNotNull('machine_id')
            ->where('record_date', '>=', Carbon::now()->subDays(30))
            ->with('machine')
            ->get();

        return $records->groupBy('machine_id')->map(function ($machineRecords) {
            $machine = $machineRecords->first()?->machine;
            $totalProduced = $machineRecords->sum('quantity_produced');
            $totalTarget = $machineRecords->sum('target_quantity');

            return [
                'machine_id' => $machine?->id,
                'machine_name' => $machine?->name ?? 'Unknown',
                'total_produced' => $totalProduced,
                'total_target' => $totalTarget,
                'achievement_rate' => $totalTarget > 0 ? ($totalProduced / $totalTarget) * 100 : null,
                'record_count' => $machineRecords->count(),
                'average_per_record' => $machineRecords->count() > 0 ? $totalProduced / $machineRecords->count() : 0,
            ];
        })->sortByDesc('total_produced')->values();
    }

    /**
     * @return Collection<int,ProductionForecast>
     */
    public function getRecentForecasts(int $teamId, int $days = 7): Collection
    {
        return ProductionForecast::forTeam($teamId)
            ->where('forecast_date', '>=', Carbon::now())
            ->where('forecast_date', '<=', Carbon::now()->addDays($days))
            ->orderBy('forecast_date')
            ->get();
    }
}
