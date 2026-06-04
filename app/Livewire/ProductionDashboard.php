<?php

namespace App\Livewire;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MineArea;
use App\Models\ProductionRecord;
use App\Services\ProductionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

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

    protected $productionService;

    protected $team;

    public int $teamId = 0;

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

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('record_date', [$this->startDate, $this->endDate]);
        }

        return $query->orderByDesc('record_date')->paginate(15);
    }

    public function getStatisticsProperty()
    {
        return $this->productionService->getProductionStatistics(
            $this->teamId,
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate)
        );
    }

    public function getTrendProperty()
    {
        $days = (int) Carbon::parse($this->startDate)->diffInDays(Carbon::parse($this->endDate)) + 1;

        return $this->productionService->getProductionTrend($this->teamId, max($days, 1));
    }

    public function getTargetsProperty()
    {
        return $this->productionService->getActiveTargets($this->teamId);
    }

    public function getForecastsProperty()
    {
        return $this->productionService->getRecentForecasts($this->teamId, 7);
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

    public function getFatigueDataProperty()
    {
        // Placeholder implementation - can be enhanced with operator fatigue tracking
        return [];
    }

    public function getFatigueStatsProperty()
    {
        return [
            'well_rested' => 0,
            'needs_monitoring' => 0,
            'high_fatigue' => 0,
            'needs_rest' => 0,
        ];
    }

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

    public function getLoadComparisonDataProperty(): array
    {
        $startDate = Carbon::parse($this->startDate);
        $endDate = Carbon::parse($this->endDate);

        // Reported: manually entered production records per machine
        $reported = ProductionRecord::forTeam($this->teamId)
            ->whereBetween('record_date', [$startDate, $endDate])
            ->whereNotNull('machine_id')
            ->with('machine:id,name,machine_type')
            ->get()
            ->groupBy('machine_id');

        // Recorded: machine sensor data (each metric reading with load_weight > 0 is a load event)
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
            $record = ProductionRecord::where('team_id', $this->teamId)->findOrFail($this->editingRecordId);
            $this->productionService->updateProductionRecord($record, [
                ...$validated,
                'notes' => $this->notes,
            ]);
            $this->showEditModal = false;
        } else {
            $this->productionService->createProductionRecord($this->teamId, [
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
        $record = ProductionRecord::where('team_id', $this->teamId)->findOrFail($id);
        $this->productionService->deleteProductionRecord($record);
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

    public function render(): \Illuminate\View\View
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
        ]);
    }
}
