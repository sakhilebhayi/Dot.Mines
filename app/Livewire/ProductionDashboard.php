<?php

namespace App\Livewire;

use App\Models\BellEquipment;
use App\Models\BellEquipmentLocationHistory;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MineArea;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\MachineKpiService;
use App\Services\ProductionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read mixed $productionRecords
 * @property-read mixed $statistics
 * @property-read mixed $trend
 * @property-read mixed $targets
 * @property-read mixed $forecasts
 * @property-read array<mixed> $summary
 * @property-read mixed $mineAreas
 * @property-read mixed $machines
 * @property-read array<mixed> $dailyChart
 * @property-read array<mixed> $materialBreakdown
 * @property-read array<mixed> $fatigueData
 * @property-read array<mixed> $fatigueStats
 * @property-read array<mixed> $productionChartData
 * @property-read array<mixed> $loadComparisonData
 * @property-read array<mixed> $areaPerformance
 * @property-read array{total_loads: int, total_payload_tonnes: float, avg_utilization: float, has_data: bool} $oemKpiSummary
 * @property-read array<int, array<string, mixed>> $bellTruckBreakdown
 */
class ProductionDashboard extends Component
{
    use WithPagination;

    public string $viewMode = 'overview'; // overview, records, targets, analytics

    public string $search = '';

    public string $dateFilter = 'month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?int $mineAreaFilter = null;

    public string $statusFilter = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingRecordId = null;

    // Form fields
    public ?string $record_date = null;

    public string $shift = 'day';

    public string $quantity_produced = '';

    public string $target_quantity = '';

    public ?int $mine_area_id = null;

    public ?int $machine_id = null;

    public string $status = 'completed';

    public string $notes = '';

    protected ?ProductionService $productionService = null;

    protected ?Team $team = null;

    public int $teamId = 0;

    private function productionService(): ProductionService
    {
        assert($this->productionService !== null);

        return $this->productionService;
    }

    public function mount()
    {
        $this->productionService = app(ProductionService::class);
        $this->team = Auth::user()->currentTeam;
        $this->teamId = $this->team?->id ?? 0;
        $this->record_date = Carbon::today()->format('Y-m-d');
        $this->endDate = Carbon::today()->format('Y-m-d');
        $this->startDate = Carbon::today()->subMonth()->format('Y-m-d');
    }

    /**
     * Ensure services and team are available after Livewire hydration.
     */
    public function hydrate()
    {
        if (! $this->productionService) {
            $this->productionService = app(ProductionService::class);
        }

        $this->team = Auth::user()->currentTeam;
        $this->teamId = $this->team?->id ?? $this->teamId;
    }

    public function updatedDateFilter(string $value): void
    {
        match ($value) {
            'day' => [$this->startDate, $this->endDate] = [Carbon::today()->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            'week' => [$this->startDate, $this->endDate] = [Carbon::today()->startOfWeek()->format('Y-m-d'), Carbon::today()->endOfWeek()->format('Y-m-d')],
            'month' => [$this->startDate, $this->endDate] = [Carbon::today()->startOfMonth()->format('Y-m-d'), Carbon::today()->endOfMonth()->format('Y-m-d')],
            'year' => [$this->startDate, $this->endDate] = [Carbon::today()->startOfYear()->format('Y-m-d'), Carbon::today()->endOfYear()->format('Y-m-d')],
            default => null,
        };
    }

    public function getProductionRecordsProperty()
    {
        $query = ProductionRecord::forTeam($this->teamId);

        if ($this->search) {
            $query->whereHas('mineArea', function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            })->orWhere('notes', 'like', "%{$this->search}%");
        }

        if ($this->mineAreaFilter) {
            $query->where('mine_area_id', $this->mineAreaFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->dateFilter) {
            $start = match ($this->dateFilter) {
                'day' => Carbon::today(),
                'week' => Carbon::today()->subWeek(),
                'month' => Carbon::today()->subMonth(),
                'year' => Carbon::today()->subYear(),
                default => null,
            };

            if ($start) {
                $query->where('record_date', '>=', $start->format('Y-m-d'));
            }
        }

        return $query->orderByDesc('record_date')->paginate(15);
    }

    public function getStatisticsProperty()
    {
        return $this->productionService()->getProductionStatistics(
            $this->teamId,
            Carbon::now()->subDays(30),
            Carbon::now()
        );
    }

    public function getTrendProperty()
    {
        return $this->productionService()->getProductionTrend($this->teamId, 30);
    }

    public function getTargetsProperty()
    {
        return $this->productionService()->getActiveTargets($this->teamId);
    }

    public function getForecastsProperty()
    {
        return $this->productionService()->getRecentForecasts($this->teamId, 7);
    }

    public function getSummaryProperty()
    {
        $stats = $this->statistics;
        $activeAreas = MineArea::forTeam($this->teamId)->where('status', 'active')->count();

        return [
            'total_loads' => $stats['total_records'] ?? 0,
            'total_cycles' => $stats['completed_records'] ?? 0,
            'total_tonnage' => round($stats['total_produced'] ?? 0, 2),
            'total_bcm' => round($stats['total_produced'] ?? 0, 2),
            'active_areas' => $activeAreas,
        ];
    }

    public function getMineAreasProperty()
    {
        return MineArea::forTeam($this->teamId)->get();
    }

    public function getMachinesProperty()
    {
        return Machine::where('team_id', $this->teamId)->get();
    }

    public function getDailyChartProperty()
    {
        $trend = $this->trend;
        if (! $trend || $trend->isEmpty()) {
            return [];
        }

        return $trend->map(function ($day) {
            return [
                'date' => $day['date'],
                'tonnage' => $day['produced'] ?? 0,
                'loads' => $day['count'] ?? 0,
            ];
        })->toArray();
    }

    public function getMaterialBreakdownProperty()
    {
        // Placeholder implementation - can be enhanced with actual material tracking
        return [];
    }

    /**
     * OEM telemetry KPI aggregates for the selected date range.
     * Pulled from every integrated source (Bell, IoT sensors, future OEMs) via MachineKpiService.
     *
     * @return array{total_loads: int, total_payload_tonnes: float, avg_utilization: float, has_data: bool}
     */
    public function getOemKpiSummaryProperty(): array
    {
        $machineIds = Machine::where('team_id', $this->teamId)->pluck('id')->all();

        return app(MachineKpiService::class)->getDailyKpiSummary(
            $machineIds,
            $this->startDate ?? today()->toDateString(),
            $this->endDate ?? today()->toDateString(),
        );
    }

    /**
     * Per-truck Bell production breakdown for the selected date range.
     * Reads from production_records (shift='oem_auto') synced from the Bell API.
     * Includes latest known GPS location from bell_equipment_location_history.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBellTruckBreakdownProperty(): array
    {
        $startDate = $this->startDate ?? today()->toDateString();
        $endDate = $this->endDate ?? today()->toDateString();

        $records = ProductionRecord::where('team_id', $this->teamId)
            ->whereBetween('record_date', [$startDate, $endDate])
            ->where('shift', 'oem_auto')
            ->whereNotNull('machine_id')
            ->with('machine')
            ->get();

        if ($records->isEmpty()) {
            return [];
        }

        // Map machine_id → equipment_key for location lookup.
        $machineIds = $records->pluck('machine_id')->unique()->values();
        $bellByMachine = BellEquipment::whereIn('machine_id', $machineIds)
            ->get()
            ->keyBy('machine_id');

        $equipmentKeys = $bellByMachine->pluck('equipment_key')->values()->all();

        // Latest location per equipment_key via correlated subquery (no window functions needed).
        $latestLocations = BellEquipmentLocationHistory::whereIn('equipment_key', $equipmentKeys)
            ->where('recorded_at', function ($sub) {
                $sub->selectRaw('MAX(inner_loc.recorded_at)')
                    ->from('bell_equipment_location_history as inner_loc')
                    ->whereColumn('inner_loc.equipment_key', 'bell_equipment_location_history.equipment_key');
            })
            ->get()
            ->keyBy('equipment_key');

        return $records
            ->groupBy('machine_id')
            ->map(function ($machineRecords, $machineId) use ($bellByMachine, $latestLocations) {
                $machine = $machineRecords->first()->machine;
                $bellEq = $bellByMachine->get($machineId);
                $location = $bellEq ? $latestLocations->get($bellEq->equipment_key) : null;

                return [
                    'machine_id' => $machineId,
                    'machine_name' => $machine?->name ?? 'Unknown Truck',
                    'serial_number' => $bellEq?->serial_number,
                    'model' => $bellEq?->model,
                    'total_loads' => (int) $machineRecords->sum('loads_moved'),
                    'total_cycles' => (int) $machineRecords->sum('cycles_completed'),
                    'total_payload_tonnes' => round((float) $machineRecords->sum('system_quantity'), 2),
                    'last_lat' => $location ? (float) $location->latitude : null,
                    'last_lng' => $location ? (float) $location->longitude : null,
                    'last_seen' => $location?->recorded_at?->diffForHumans(),
                    'last_seen_raw' => $location?->recorded_at?->toIso8601String(),
                    'last_record_date' => $machineRecords->max('record_date'),
                ];
            })
            ->sortByDesc('total_payload_tonnes')
            ->values()
            ->toArray();
    }

    /** @return array<empty> */
    public function getFatigueDataProperty(): array
    {
        // Placeholder implementation - can be enhanced with operator fatigue tracking
        return [];
    }

    /** @return array<string, int> */
    public function getFatigueStatsProperty(): array
    {
        return [
            'well_rested' => 0,
            'needs_monitoring' => 0,
            'high_fatigue' => 0,
            'needs_rest' => 0,
        ];
    }

    /** @return array<string, mixed> */
    public function getProductionChartDataProperty(): array
    {
        $startDate = Carbon::parse($this->startDate);
        $endDate = Carbon::parse($this->endDate);

        $records = ProductionRecord::forTeam($this->teamId)
            ->whereBetween('record_date', [$startDate, $endDate])
            ->with('machine')
            ->orderBy('record_date')
            ->get();

        if ($records->isEmpty()) {
            return ['daily' => [], 'per_machine' => []];
        }

        $daily = $records->groupBy(fn ($r) => $r->record_date->format('Y-m-d'))
            ->map(fn ($day) => [
                'date' => $day->first()->record_date->format('M d'),
                'tonnage' => round((float) $day->sum('quantity_produced'), 2),
                'target' => round((float) $day->sum('target_quantity'), 2),
                'loads' => $day->count(),
            ])->values()->toArray();

        $perMachine = $records->groupBy('machine_id')
            ->map(function ($machineRecords) {
                $machine = $machineRecords->first()->machine;

                return [
                    'machine_name' => $machine?->name ?? 'Unassigned',
                    'tonnage' => round((float) $machineRecords->sum('quantity_produced'), 2),
                    'loads' => $machineRecords->count(),
                ];
            })->values()->toArray();

        return ['daily' => $daily, 'per_machine' => $perMachine];
    }

    /** @return array<string, mixed> */
    public function getLoadComparisonDataProperty(): array
    {
        $startDate = Carbon::parse($this->startDate);
        $endDate = Carbon::parse($this->endDate);

        $reported = ProductionRecord::forTeam($this->teamId)
            ->whereBetween('record_date', [$startDate, $endDate])
            ->whereNotNull('machine_id')
            ->with('machine:id,name,machine_type')
            ->get()
            ->groupBy('machine_id');

        $recorded = MachineMetric::where('team_id', $this->teamId)
            ->whereBetween('recorded_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->where('load_weight', '>', 0)
            ->get()
            ->groupBy('machine_id');

        $machineIds = $reported->keys()->merge($recorded->keys())->unique();

        if ($machineIds->isEmpty()) {
            return [];
        }

        $machines = Machine::where('team_id', $this->teamId)
            ->whereIn('id', $machineIds)
            ->get()
            ->keyBy('id');

        return $machineIds->map(function ($machineId) use ($reported, $recorded, $machines) {
            $machine = $machines->get($machineId);
            $reportedRecords = $reported->get($machineId, collect());
            $recordedMetrics = $recorded->get($machineId, collect());

            $reportedTonnage = round((float) $reportedRecords->sum('quantity_produced'), 2);
            $recordedTonnage = round((float) $recordedMetrics->sum('load_weight'), 2);

            return [
                'machine_id' => $machineId,
                'machine_name' => $machine?->name ?? "Machine #{$machineId}",
                'machine_type' => $machine?->machine_type ?? 'unknown',
                'reported_loads' => $reportedRecords->count(),
                'reported_tonnage' => $reportedTonnage,
                'recorded_loads' => $recordedMetrics->count(),
                'recorded_tonnage' => $recordedTonnage,
                'variance' => round($reportedTonnage - $recordedTonnage, 2),
            ];
        })->values()->toArray();
    }

    public function getAreaPerformanceProperty()
    {
        $mineAreas = $this->mineAreas;
        if (! $mineAreas || $mineAreas->isEmpty()) {
            return [];
        }

        return $mineAreas->map(function ($area) {
            $records = ProductionRecord::where('team_id', $this->teamId)
                ->where('mine_area_id', $area->id)
                ->betweenDates(Carbon::parse($this->startDate), Carbon::parse($this->endDate))
                ->get();

            return [
                'area_name' => $area->name,
                'area_type' => $area->status ?? 'active',
                'loads' => $records->count(),
                'cycles' => $records->count(),
                'tonnage' => $records->sum('quantity_produced') ?? 0,
                'bcm' => $records->sum('quantity_produced') ?? 0, // Using quantity_produced as BCM proxy
            ];
        })->filter(function ($area) {
            return $area['loads'] > 0;
        })->values()->toArray();
    }

    public function openCreateModal()
    {
        $this->showCreateModal = true;
        $this->resetForm();
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function openEditModal($id)
    {
        $record = ProductionRecord::where('team_id', $this->teamId)->findOrFail($id);
        $this->editingRecordId = $id;
        $this->record_date = $record->record_date->format('Y-m-d');
        $this->shift = $record->shift;
        $this->quantity_produced = $record->quantity_produced;
        $this->target_quantity = $record->target_quantity;
        $this->mine_area_id = $record->mine_area_id;
        $this->machine_id = $record->machine_id;
        $this->status = $record->status;
        $this->notes = $record->notes ?? '';
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->resetForm();
    }

    public function saveRecord()
    {
        $validated = $this->validate([
            'record_date' => 'required|date',
            'shift' => 'required|in:day,night,continuous',
            'quantity_produced' => 'required|numeric|min:0',
            'target_quantity' => 'nullable|numeric|min:0',
            'mine_area_id' => 'nullable|exists:mine_areas,id',
            'machine_id' => 'nullable|exists:machines,id',
            'status' => 'required|in:completed,in-progress,pending,paused',
        ]);

        if ($this->editingRecordId) {
            /** @var ProductionRecord $record */
            $record = ProductionRecord::where('team_id', $this->teamId)->findOrFail($this->editingRecordId);
            $this->productionService()->updateProductionRecord($record, [
                ...$validated,
                'notes' => $this->notes,
            ]);
            $this->showEditModal = false;
        } else {
            $this->productionService()->createProductionRecord($this->teamId, [
                ...$validated,
                'notes' => $this->notes,
            ]);
            $this->showCreateModal = false;
        }

        $this->resetForm();
        $this->dispatch('record-saved');
    }

    public function deleteRecord($id)
    {
        /** @var ProductionRecord $record */
        $record = ProductionRecord::where('team_id', $this->teamId)->findOrFail($id);
        $this->productionService()->deleteProductionRecord($record);
    }

    public function resetForm()
    {
        $this->record_date = Carbon::today()->format('Y-m-d');
        $this->shift = 'day';
        $this->quantity_produced = '';
        $this->target_quantity = '';
        $this->mine_area_id = null;
        $this->machine_id = null;
        $this->status = 'completed';
        $this->notes = '';
        $this->editingRecordId = null;
    }

    public function switchView($mode)
    {
        $this->viewMode = $mode;
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.production-dashboard', [
            'records' => $this->productionRecords,
            'summary' => $this->summary,
            'statistics' => $this->statistics,
            'trend' => $this->trend,
            'targets' => $this->targets,
            'forecasts' => $this->forecasts,
            'mineAreas' => $this->mineAreas,
            'machines' => $this->machines,
            'dailyChart' => $this->dailyChart,
            'materialBreakdown' => $this->materialBreakdown,
            'fatigueData' => $this->fatigueData,
            'fatigueStats' => $this->fatigueStats,
            'areaPerformance' => $this->areaPerformance,
            'productionChartData' => $this->productionChartData,
            'loadComparisonData' => $this->loadComparisonData,
            'bellKpiSummary' => $this->oemKpiSummary,
            'bellTruckBreakdown' => $this->bellTruckBreakdown,
        ]);
    }
}
