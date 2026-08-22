<?php

namespace App\Livewire;

use App\Models\Machine;
use App\Models\MineArea;
use App\Models\OperatorFatigue;
use App\Models\ProductionForecast;
use App\Models\ProductionRecord;
use App\Models\ProductionTarget;
use App\Models\Team;
use App\Services\ProductionService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, ProductionRecord> $productionRecords
 * @property-read array<string, mixed> $statistics
 * @property-read Collection<string, array<string, mixed>> $trend
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductionTarget> $targets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductionForecast> $forecasts
 * @property-read array<string, mixed> $summary
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MineArea> $mineAreas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Machine> $machines
 * @property-read array{}|Collection<int, array<string, mixed>> $dailyChart
 * @property-read array{} $materialBreakdown
 * @property-read array<int, array{operator_name: string, machine_name: string|null, shift_type: string, hours_worked: float, consecutive_days: float, fatigue_score: int, alert_level: string}> $fatigueData
 * @property-read array<string, int> $fatigueStats
 * @property-read array{}|Collection<int, array<string, mixed>> $areaPerformance
 */
#[Lazy]
class ProductionDashboard extends Component
{
    /**
     * Skeleton shown while this page lazy-loads -- the page shell paints
     * immediately instead of blocking on mount()'s data queries.
     *
     * @psalm-suppress PossiblyUnusedMethod -- invoked by Livewire's lazy-loading lifecycle
     */
    public function placeholder(): View
    {
        return view('livewire.placeholders.dashboard');
    }

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

    public function mount(): void
    {
        $this->productionService = app(ProductionService::class);
        $this->team = Auth::user()->currentTeam;
        $this->teamId = $this->team?->id ?? 0;
        $this->record_date = Carbon::today()->format('Y-m-d');
        // Default period is "Month": start of the current calendar month
        // through today, matching what the Month quick-toggle produces.
        $this->endDate = Carbon::today()->format('Y-m-d');
        $this->startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
    }

    /**
     * Quick period toggle. Sets BOTH the active period label and the visible
     * date pickers, so the pickers always show the range actually queried:
     * day = today only; week/month/year = start of the current calendar
     * period (ISO week, Monday) through today.
     */
    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['day', 'week', 'month', 'year'], true)) {
            return;
        }

        $this->dateFilter = $period;
        $this->endDate = Carbon::today()->format('Y-m-d');
        $this->startDate = (match ($period) {
            'day' => Carbon::today(),
            'week' => Carbon::today()->startOfWeek(),
            'month' => Carbon::today()->startOfMonth(),
            'year' => Carbon::today()->startOfYear(),
        })->format('Y-m-d');

        $this->resetPage();
    }

    /**
     * Manually editing either picker means the user is no longer on a quick
     * period -- mark the range as Custom so the previous toggle does not
     * stay highlighted for a range it no longer describes.
     */
    public function updatedStartDate(): void
    {
        $this->markCustomRange();
    }

    public function updatedEndDate(): void
    {
        $this->markCustomRange();
    }

    private function markCustomRange(): void
    {
        // A cleared date input arrives as '' -- treat it as absent so no
        // consumer ever hands Carbon::parse an empty string (a 500).
        if ($this->startDate === '') {
            $this->startDate = null;
        }
        if ($this->endDate === '') {
            $this->endDate = null;
        }

        // Keep the range valid: an end before the start is clamped.
        if ($this->hasDateRange() && $this->startDate > $this->endDate) {
            $this->endDate = $this->startDate;
        }

        $this->dateFilter = 'custom';
        $this->resetPage();
    }

    /**
     * Both pickers hold a usable date (neither null nor cleared-to-empty).
     */
    private function hasDateRange(): bool
    {
        return $this->startDate !== null && $this->startDate !== ''
            && $this->endDate !== null && $this->endDate !== '';
    }

    /**
     * Ensure services and team are available after Livewire hydration.
     */
    public function hydrate(): void
    {
        if (! $this->productionService) {
            $this->productionService = app(ProductionService::class);
        }

        $this->team = Auth::user()->currentTeam;
        $this->teamId = $this->team?->id ?? $this->teamId;
    }

    /**
     * @return LengthAwarePaginator<int, ProductionRecord>
     */
    public function getProductionRecordsProperty(): LengthAwarePaginator
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

        // Single source of truth: the records table follows the same
        // startDate/endDate range as every KPI and chart on the page. The
        // quick toggles used to drive a separate rolling-window filter here
        // while the pickers drove the charts -- two different answers on
        // one screen.
        if ($this->hasDateRange()) {
            $query->betweenDates(Carbon::parse($this->startDate), Carbon::parse($this->endDate));
        }

        return $query->orderByDesc('record_date')->paginate(15);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatisticsProperty(): array
    {
        // KPI tiles used to be pinned to a fixed last-30-days window no
        // matter what period the user selected.
        if (! $this->hasDateRange()) {
            return $this->productionService()->getProductionStatistics(
                $this->teamId,
                Carbon::today()->startOfMonth(),
                Carbon::today()->endOfDay()
            );
        }

        return $this->productionService()->getProductionStatistics(
            $this->teamId,
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate)->endOfDay()
        );
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function getTrendProperty(): Collection
    {
        // The daily chart follows the selected range too (was fixed 30 days).
        return $this->productionService()->getProductionTrend(
            $this->teamId,
            30,
            $this->hasDateRange() ? Carbon::parse($this->startDate) : null,
            $this->hasDateRange() ? Carbon::parse($this->endDate) : null,
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductionTarget>
     */
    public function getTargetsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->productionService()->getActiveTargets($this->teamId);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductionForecast>
     */
    public function getForecastsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->productionService()->getRecentForecasts($this->teamId, 7);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummaryProperty(): array
    {
        $stats = $this->statistics;
        $activeAreas = MineArea::forTeam($this->teamId)->where('status', 'active')->count();

        return [
            // Real load/cycle counts (telemetry records carry them in
            // metadata; manual records still count as one each) -- the
            // record-count proxy undercounted badly once integration
            // records aggregating a whole day of loads existed.
            'total_loads' => $stats['total_loads'] ?? $stats['total_records'] ?? 0,
            'total_cycles' => $stats['total_cycles'] ?? $stats['completed_records'] ?? 0,
            'total_tonnage' => round($stats['total_produced'] ?? 0, 2),
            'total_bcm' => round($stats['total_produced'] ?? 0, 2),
            'active_areas' => $activeAreas,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MineArea>
     */
    public function getMineAreasProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return MineArea::forTeam($this->teamId)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Machine>
     */
    public function getMachinesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Machine::where('team_id', $this->teamId)->get();
    }

    /**
     * @return array{}|Collection<int, array<string, mixed>>
     */
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
                'loads' => $day['loads'] ?? $day['count'] ?? 0,
            ];
        })->toArray();
    }

    /**
     * @return array{}
     */
    public function getMaterialBreakdownProperty(): array
    {
        // Production records carry no material dimension on this schema, so
        // there is nothing real to aggregate -- the blade renders its
        // "No material data available" empty state instead of a fabricated
        // breakdown.
        return [];
    }

    /**
     * Most recent fatigue entry per operator over the last 7 days, in the
     * shape the fatigue table renders. Reads the same OperatorFatigue rows
     * as OperatorFatigueTracker -- that model's fatigue_score/alert_level
     * is the single source of truth; nothing is reclassified here.
     *
     * @return array<int, array{operator_name: string, machine_name: string|null, shift_type: string, hours_worked: float, consecutive_days: float, fatigue_score: int, alert_level: string}>
     */
    public function getFatigueDataProperty(): array
    {
        if (! $this->teamId) {
            return [];
        }

        return OperatorFatigue::query()
            ->where('team_id', $this->teamId)
            ->where('shift_date', '>=', Carbon::today()->subDays(7)->startOfDay())
            ->with(['user', 'machine'])
            ->get()
            ->sortBy([['shift_date', 'desc'], ['fatigue_score', 'desc']])
            ->unique('user_id')
            ->toBase()
            ->map(fn (OperatorFatigue $fatigue): array => [
                'operator_name' => $fatigue->user?->name ?? 'Unknown operator',
                'machine_name' => $fatigue->machine?->name,
                'shift_type' => $fatigue->shift_type,
                'hours_worked' => $fatigue->hours_worked,
                'consecutive_days' => $fatigue->consecutive_days,
                'fatigue_score' => $fatigue->fatigue_score,
                'alert_level' => $fatigue->alert_level,
            ])
            ->values()
            ->all();
    }

    /**
     * Summary-card counts derived from the SAME rows the fatigue table
     * shows, bucketed by OperatorFatigue's canonical alert levels:
     * none/low -> well rested, medium -> needs monitoring,
     * high -> high fatigue, critical -> needs rest. Buckets are disjoint,
     * so the four cards always sum to the number of operators listed.
     *
     * @return array<string, int>
     */
    public function getFatigueStatsProperty()
    {
        $levels = array_count_values(array_column($this->getFatigueDataProperty(), 'alert_level'));

        return [
            'well_rested' => ($levels['none'] ?? 0) + ($levels['low'] ?? 0),
            'needs_monitoring' => $levels['medium'] ?? 0,
            'high_fatigue' => $levels['high'] ?? 0,
            'needs_rest' => $levels['critical'] ?? 0,
        ];
    }

    /**
     * @return array{}|Collection<int, array<string, mixed>>
     */
    public function getAreaPerformanceProperty()
    {
        $mineAreas = $this->mineAreas;
        if (! $mineAreas || $mineAreas->isEmpty() || ! $this->hasDateRange()) {
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
                'loads' => (int) $records->sum(fn ($record) => $this->productionService()->recordLoads($record)),
                'cycles' => (int) $records->sum(fn ($record) => $this->productionService()->recordCycles($record)),
                'tonnage' => $records->sum('quantity_produced') ?? 0,
                'bcm' => $records->sum('quantity_produced') ?? 0, // Using quantity_produced as BCM proxy
            ];
        })->filter(function ($area) {
            return $area['loads'] > 0;
        })->values()->toArray();
    }

    public function openCreateModal(): void
    {
        $this->showCreateModal = true;
        $this->resetForm();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    /**
     * @param  int|string  $id
     */
    public function openEditModal($id): void
    {
        $record = ProductionRecord::where('team_id', $this->teamId)->findOrFail($id);
        $this->editingRecordId = (int) $id;
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

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetForm();
    }

    public function saveRecord(): void
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

    /**
     * @param  int|string  $id
     */
    public function deleteRecord($id): void
    {
        /** @var ProductionRecord $record */
        $record = ProductionRecord::where('team_id', $this->teamId)->findOrFail($id);
        $this->productionService()->deleteProductionRecord($record);
    }

    public function resetForm(): void
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

    /**
     * @param  string  $mode
     */
    public function switchView($mode): void
    {
        $this->viewMode = $mode;
        $this->resetPage();
    }

    public function render(): View
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
