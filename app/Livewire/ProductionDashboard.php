<?php

namespace App\Livewire;

use App\Models\Machine;
use App\Models\MineArea;
use App\Models\ProductionRecord;
use App\Models\Team;
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
        $this->notes = $record->notes;
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
        ]);
    }
}
