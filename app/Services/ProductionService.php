<?php

namespace App\Services;

use App\Models\ProductionForecast;
use App\Models\ProductionRecord;
use App\Models\ProductionTarget;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionService
{
    /**
     * @return LengthAwarePaginator<int, ProductionRecord>
     */
    public function getProductionByTeam(int $teamId, ?Carbon $startDate = null, ?Carbon $endDate = null): LengthAwarePaginator
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
            ];
        });
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
