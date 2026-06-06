<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Models\MinePlanUpload;
use App\Models\ProductionRecord;
use App\Models\ProductionTarget;
use App\Services\FileUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MineAreaDetail extends Component
{
    use WithFileUploads, WithPagination;

    public MineArea $mineArea;

    public string $activeTab = 'overview';

    // Machine Assignment
    public bool $showAssignModal = false;

    public ?int $selectedMachineId = null;

    public string $assignmentReason = '';

    // Production Tracking
    public bool $showProductionModal = false;

    public string $productionDate = '';

    public string $productionShift = 'day';

    public ?float $quantityProduced = null;

    public ?float $systemQuantity = null;

    public ?float $targetQuantity = null;

    public string $productionUnit = 'tonnes';

    public ?int $productionMachineId = null;

    public string $productionNotes = '';

    public string $productionPeriod = 'week'; // week, month, quarter

    // Production comparison filters
    public string $comparisonPeriod = '30'; // days: 7, 14, 30, 90

    public string $comparisonMachineId = ''; // '' = all machines

    // Production Target
    public bool $showTargetModal = false;

    public string $targetPeriodType = 'monthly';

    public string $targetStartDate = '';

    public string $targetEndDate = '';

    public ?float $targetValue = null;

    public string $targetUnit = 'tonnes';

    public string $targetDescription = '';

    // Mine Plan Upload
    public bool $showUploadModal = false;

    public string $planTitle = '';

    public string $planDescription = '';

    public ?UploadedFile $planFile = null;

    public string $planFileType = 'pdf';

    public string $planVersion = '1.0';

    public string $planStatus = 'draft';

    public string $planEffectiveDate = '';

    // Area Alert
    public bool $showAlertModal = false;

    public string $alertTitle = '';

    public string $alertDescription = '';

    public string $alertType = 'area';

    public string $alertPriority = 'medium';

    // Geofence linking
    public bool $showGeofenceModal = false;

    public ?int $selectedGeofenceId = null;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'selectedMachineId' => 'required_if:showAssignModal,true|nullable|exists:machines,id',
            'assignmentReason' => 'nullable|string|max:255',
            'productionDate' => 'required_if:showProductionModal,true|nullable|date',
            'productionShift' => 'in:day,night,continuous',
            'quantityProduced' => 'required_if:showProductionModal,true|nullable|numeric|min:0',
            'targetQuantity' => 'nullable|numeric|min:0',
            'productionUnit' => 'in:tonnes,cubic_meters,loads,trips',
            'productionMachineId' => 'nullable|exists:machines,id',
            'targetPeriodType' => 'in:daily,weekly,monthly,quarterly,yearly',
            'targetStartDate' => 'required_if:showTargetModal,true|nullable|date',
            'targetEndDate' => 'required_if:showTargetModal,true|nullable|date|after_or_equal:targetStartDate',
            'targetValue' => 'required_if:showTargetModal,true|nullable|numeric|min:0',
            'planTitle' => 'required_if:showUploadModal,true|nullable|string|max:255',
            'planFile' => 'required_if:showUploadModal,true|nullable|file|max:51200',
            'alertTitle' => 'required_if:showAlertModal,true|nullable|string|max:255',
            'alertDescription' => 'nullable|string|max:1000',
            'alertPriority' => 'in:critical,high,medium,low',
        ];
    }

    public function mount(MineArea $mineArea): void
    {
        $team = Auth::user()->currentTeam;
        if ($mineArea->team_id !== $team->id) {
            abort(403);
        }
        $this->mineArea = $mineArea;
        $this->productionDate = now()->toDateString();
        $this->targetStartDate = now()->startOfMonth()->toDateString();
        $this->targetEndDate = now()->endOfMonth()->toDateString();
        $this->planEffectiveDate = now()->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    // === MACHINE ASSIGNMENT ===

    public function openAssignModal(): void
    {
        $this->showAssignModal = true;
        $this->selectedMachineId = null;
        $this->assignmentReason = '';
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->selectedMachineId = null;
        $this->assignmentReason = '';
    }

    public function assignMachine(): void
    {
        $this->validate([
            'selectedMachineId' => 'required|exists:machines,id',
        ]);

        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->findOrFail($this->selectedMachineId);

        // Update machine's mine_area_id if column exists
        if (Schema::hasColumn('machines', 'mine_area_id')) {
            $machine->update(['mine_area_id' => $this->mineArea->id]);
        }

        // Record assignment history
        MachineAreaAssignment::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'mine_area_id' => $this->mineArea->id,
            'assigned_by' => Auth::id(),
            'assigned_at' => now(),
            'reason' => $this->assignmentReason ?: null,
        ]);

        $this->closeAssignModal();
        $this->dispatch('notify', ...['message' => "{$machine->name} assigned to {$this->mineArea->name}", 'type' => 'success']);
    }

    public function unassignMachine(int $machineId): void
    {
        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->findOrFail($machineId);

        // Close active assignment record
        MachineAreaAssignment::where('machine_id', $machine->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        // Try to find another active mine area to assign the machine to.
        $otherArea = MineArea::where('team_id', $team->id)
            ->where('status', 'active')
            ->where('id', '!=', $this->mineArea->id)
            ->first();

        if ($otherArea) {
            if (Schema::hasColumn('machines', 'mine_area_id')) {
                $machine->update(['mine_area_id' => $otherArea->id]);
            }
            $this->dispatch('notify', ...['message' => "{$machine->name} reassigned to {$otherArea->name} (cannot leave unassigned)", 'type' => 'success']);
        } else {
            // No other active area exists — do not allow unassigning to null to preserve invariant
            $this->dispatch('notify', ...['message' => "Cannot unassign {$machine->name}; at least one active mine area must be set. Assign to another area first.", 'type' => 'error']);

            return;
        }
    }

    // === PRODUCTION TRACKING ===

    public function openProductionModal(): void
    {
        $this->showProductionModal = true;
        $this->productionDate = now()->toDateString();
        $this->quantityProduced = null;
        $this->systemQuantity = null;
        $this->targetQuantity = null;
        $this->productionNotes = '';
        $this->productionMachineId = null;
    }

    public function closeProductionModal(): void
    {
        $this->showProductionModal = false;
    }

    public function saveProductionRecord(): void
    {
        $this->validate([
            'productionDate' => 'required|date',
            'quantityProduced' => 'required|numeric|min:0',
            'productionShift' => 'required|in:day,night,continuous',
        ]);

        $team = Auth::user()->currentTeam;

        ProductionRecord::create([
            'team_id' => $team->id,
            'mine_area_id' => $this->mineArea->id,
            'machine_id' => $this->productionMachineId ?: null,
            'record_date' => $this->productionDate,
            'shift' => $this->productionShift,
            'quantity_produced' => $this->quantityProduced,
            'system_quantity' => $this->systemQuantity ?: null,
            'target_quantity' => $this->targetQuantity,
            'unit' => $this->productionUnit,
            'notes' => $this->productionNotes ?: null,
            'status' => 'completed',
        ]);

        $this->closeProductionModal();
        $this->dispatch('notify', ...['message' => 'Production record saved successfully', 'type' => 'success']);
    }

    public function openTargetModal(): void
    {
        $this->showTargetModal = true;
        $this->targetValue = null;
        $this->targetDescription = '';
    }

    public function closeTargetModal(): void
    {
        $this->showTargetModal = false;
    }

    public function saveProductionTarget(): void
    {
        $this->validate([
            'targetStartDate' => 'required|date',
            'targetEndDate' => 'required|date|after_or_equal:targetStartDate',
            'targetValue' => 'required|numeric|min:0',
        ]);

        $team = Auth::user()->currentTeam;

        ProductionTarget::create([
            'team_id' => $team->id,
            'mine_area_id' => $this->mineArea->id,
            'period_type' => $this->targetPeriodType,
            'start_date' => $this->targetStartDate,
            'end_date' => $this->targetEndDate,
            'target_quantity' => $this->targetValue,
            'unit' => $this->targetUnit,
            'description' => $this->targetDescription ?: null,
            'is_active' => true,
        ]);

        $this->closeTargetModal();
        $this->dispatch('notify', ...['message' => 'Production target created successfully', 'type' => 'success']);
    }

    // === MINE PLAN UPLOADS ===

    public function openUploadModal(): void
    {
        $this->showUploadModal = true;
        $this->planTitle = '';
        $this->planDescription = '';
        $this->planFile = null;
        $this->planVersion = '1.0';
        $this->planStatus = 'draft';
        $this->planEffectiveDate = now()->toDateString();
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->planFile = null;
    }

    public function uploadMinePlan(): void
    {
        $this->validate([
            'planTitle' => 'required|string|max:255',
            'planFile' => 'required|file|max:51200',
        ]);

        $team = Auth::user()->currentTeam;

        $file = $this->planFile;

        if ($file === null) {
            $this->addError('planFile', 'Please select a file to upload.');

            return;
        }

        try {
            $uploader = new FileUploadService;
            $result = $uploader->storeMinePlan($file, $team->id, $this->mineArea->id);

            // Map extension to type
            $extension = strtolower($file->getClientOriginalExtension());
            $fileTypeMap = [
                'pdf' => 'pdf',
                'dwg' => 'dwg',
                'dxf' => 'dxf',
                'kml' => 'kml',
                'kmz' => 'kmz',
                'shp' => 'shapefile',
                'png' => 'image',
                'jpg' => 'image',
                'jpeg' => 'image',
                'gif' => 'image',
                'tif' => 'image',
                'tiff' => 'image',
            ];
            $fileType = $fileTypeMap[$extension] ?? $extension;

            MinePlanUpload::create([
                'team_id' => $team->id,
                'mine_area_id' => $this->mineArea->id,
                'uploaded_by' => Auth::id(),
                'title' => $this->planTitle,
                'description' => $this->planDescription ?: null,
                'file_name' => $result['file_name'],
                'file_path' => $result['path'],
                'file_type' => $fileType,
                'file_size' => $result['size'],
                'version' => $this->planVersion,
                'status' => $this->planStatus,
                'effective_date' => $this->planEffectiveDate ?: null,
                'metadata' => array_merge($this->mineArea->metadata ?? [], ['disk' => $result['disk']]),
            ]);

            $this->closeUploadModal();
            $this->dispatch('notify', ...['message' => 'Mine plan uploaded successfully', 'type' => 'success']);

        } catch (\Exception $e) {
            Log::error('Failed to upload mine plan', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ...['message' => 'Failed to upload mine plan: '.$e->getMessage(), 'type' => 'error']);
        }
    }

    public function deleteMinePlan(int $planId): void
    {
        $team = Auth::user()->currentTeam;
        $plan = MinePlanUpload::where('team_id', $team->id)->findOrFail($planId);
        $disk = data_get($plan->metadata, 'disk', 'public');
        Storage::disk($disk)->delete($plan->file_path);
        $plan->delete();

        $this->dispatch('notify', ...['message' => 'Mine plan deleted', 'type' => 'success']);
    }

    public function activateMinePlan(int $planId): void
    {
        $team = Auth::user()->currentTeam;
        $plan = MinePlanUpload::where('team_id', $team->id)->findOrFail($planId);
        $plan->update(['status' => 'active']);

        $this->dispatch('notify', ...['message' => 'Mine plan activated', 'type' => 'success']);
    }

    public function archiveMinePlan(int $planId): void
    {
        $team = Auth::user()->currentTeam;
        $plan = MinePlanUpload::where('team_id', $team->id)->findOrFail($planId);
        $plan->update(['status' => 'archived']);

        $this->dispatch('notify', ...['message' => 'Mine plan archived', 'type' => 'success']);
    }

    // === AREA-SPECIFIC ALERTS ===

    public function openAlertModal(): void
    {
        $this->showAlertModal = true;
        $this->alertTitle = '';
        $this->alertDescription = '';
        $this->alertType = 'area';
        $this->alertPriority = 'medium';
    }

    public function closeAlertModal(): void
    {
        $this->showAlertModal = false;
    }

    public function createAreaAlert(): void
    {
        $this->validate([
            'alertTitle' => 'required|string|max:255',
            'alertPriority' => 'required|in:critical,high,medium,low',
        ]);

        $team = Auth::user()->currentTeam;

        Alert::create([
            'team_id' => $team->id,
            'mine_area_id' => $this->mineArea->id,
            'type' => $this->alertType,
            'title' => $this->alertTitle,
            // Ensure description is not null (DB requires NOT NULL)
            'description' => $this->alertDescription ?: '',
            'priority' => $this->alertPriority,
            'status' => 'active',
            'triggered_at' => now(),
            'metadata' => [
                'created_by' => Auth::id(),
                'mine_area_name' => $this->mineArea->name,
            ],
        ]);

        $this->closeAlertModal();
        $this->dispatch('notify', ...['message' => 'Area alert created', 'type' => 'success']);
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->findOrFail($alertId);
        $alert->acknowledge(Auth::id());

        $this->dispatch('notify', ...['message' => 'Alert acknowledged', 'type' => 'success']);
    }

    public function resolveAlert(int $alertId): void
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->findOrFail($alertId);
        $alert->resolve(Auth::id());

        $this->dispatch('notify', ...['message' => 'Alert resolved', 'type' => 'success']);
    }

    // === GEOFENCE INTEGRATION ===

    public function openGeofenceModal(): void
    {
        $this->showGeofenceModal = true;
        $this->selectedGeofenceId = null;
    }

    public function closeGeofenceModal(): void
    {
        $this->showGeofenceModal = false;
    }

    public function linkGeofence(): void
    {
        $this->validate([
            'selectedGeofenceId' => 'required|exists:geofences,id',
        ]);

        $team = Auth::user()->currentTeam;
        $geofence = Geofence::where('team_id', $team->id)->findOrFail($this->selectedGeofenceId);
        $geofence->update(['mine_area_id' => $this->mineArea->id]);

        $this->closeGeofenceModal();
        $this->dispatch('notify', ...['message' => "{$geofence->name} linked to {$this->mineArea->name}", 'type' => 'success']);
    }

    public function unlinkGeofence(int $geofenceId): void
    {
        $team = Auth::user()->currentTeam;
        $geofence = Geofence::where('team_id', $team->id)->findOrFail($geofenceId);
        $geofence->update(['mine_area_id' => null]);

        $this->dispatch('notify', ...['message' => "{$geofence->name} unlinked from area", 'type' => 'success']);
    }

    // === RENDER ===

    public function render(): View
    {
        $team = Auth::user()->currentTeam;

        // Refresh mine area with counts (guard columns that may not exist in all envs)
        $countRelations = ['geofences', 'minePlanUploads', 'productionRecords'];
        if (Schema::hasColumn('machines', 'mine_area_id')) {
            $countRelations[] = 'machines';
        }
        $this->mineArea->loadCount($countRelations);

        // Assigned machines
        $hasMineAreaId = Schema::hasColumn('machines', 'mine_area_id');
        if ($hasMineAreaId) {
            $assignedMachines = Machine::where('team_id', $team->id)
                ->where('mine_area_id', $this->mineArea->id)
                ->orderBy('name')
                ->get();

            // Available machines (not assigned to this area)
            $availableMachines = Machine::where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNull('mine_area_id')
                        ->orWhere('mine_area_id', '!=', $this->mineArea->id);
                })
                ->orderBy('name')
                ->get();
        } else {
            // Column missing – show all team machines as available, none as assigned
            $assignedMachines = collect();
            $availableMachines = Machine::where('team_id', $team->id)->orderBy('name')->get();
        }

        // Assignment history
        $assignmentHistory = MachineAreaAssignment::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $team->id)
            ->with(['machine', 'assignedByUser'])
            ->orderBy('assigned_at', 'desc')
            ->limit(20)
            ->get();

        // Production records
        $productionRecords = ProductionRecord::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $team->id)
            ->with('machine')
            ->orderBy('record_date', 'desc')
            ->paginate(15);

        // Production summary
        $productionSummary = $this->getProductionSummary($team->id);

        // Active targets
        $activeTargets = ProductionTarget::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('start_date', 'desc')
            ->get();

        // Mine plan uploads
        $minePlans = MinePlanUpload::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $team->id)
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        // Area alerts
        $areaAlerts = Alert::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $team->id)
            ->orderBy('triggered_at', 'desc')
            ->limit(50)
            ->get();

        $activeAlertCount = $areaAlerts->where('status', 'active')->count();

        // Linked geofences
        $linkedGeofences = Geofence::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $team->id)
            ->withCount('entries')
            ->get();

        // Available geofences (not linked)
        $availableGeofences = Geofence::where('team_id', $team->id)
            ->where(function ($q) {
                $q->whereNull('mine_area_id')
                    ->orWhere('mine_area_id', '!=', $this->mineArea->id);
            })
            ->orderBy('name')
            ->get();

        return view('livewire.mine-area-detail', [
            'assignedMachines' => $assignedMachines,
            'availableMachines' => $availableMachines,
            'assignmentHistory' => $assignmentHistory,
            'productionRecords' => $productionRecords,
            'productionSummary' => $productionSummary,
            'activeTargets' => $activeTargets,
            'minePlans' => $minePlans,
            'areaAlerts' => $areaAlerts,
            'activeAlertCount' => $activeAlertCount,
            'linkedGeofences' => $linkedGeofences,
            'availableGeofences' => $availableGeofences,
            'comparisonData' => $this->buildComparisonData($team->id),
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getProductionSummary(int $teamId): array
    {
        $now = now();

        $todayProduction = ProductionRecord::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $teamId)
            ->where('record_date', $now->toDateString())
            ->sum('quantity_produced');

        $weekProduction = ProductionRecord::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $teamId)
            ->whereBetween('record_date', [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()])
            ->sum('quantity_produced');

        $monthProduction = ProductionRecord::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $teamId)
            ->whereMonth('record_date', $now->month)
            ->whereYear('record_date', $now->year)
            ->sum('quantity_produced');

        // Get active monthly target
        $monthTarget = ProductionTarget::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->where('start_date', '<=', $now->toDateString())
            ->where('end_date', '>=', $now->toDateString())
            ->first();

        $targetValue = $monthTarget ? $monthTarget->target_quantity : 0;
        $targetProgress = $targetValue > 0 ? round(($monthProduction / $targetValue) * 100, 1) : 0;

        return [
            'today' => $todayProduction,
            'week' => $weekProduction,
            'month' => $monthProduction,
            'target' => $targetValue,
            'target_progress' => min($targetProgress, 100),
            'target_unit' => $monthTarget->unit ?? 'tonnes',
        ];
    }

    /**
     * Build comparison data: system-recorded vs operator-reported quantities.
     * Grouped by date within the selected period, optionally filtered by machine.
     *
     * @return array{has_system_data: bool, rows: array, machines: mixed}
     */
    /** @return array<string, mixed> */
    private function buildComparisonData(int $teamId): array
    {
        $days = (int) ($this->comparisonPeriod ?: 30);
        $start = now()->subDays($days - 1)->startOfDay();

        $query = ProductionRecord::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $teamId)
            ->where('record_date', '>=', $start->toDateString())
            ->orderBy('record_date');

        if ($this->comparisonMachineId !== '') {
            $query->where('machine_id', (int) $this->comparisonMachineId);
        }

        $records = $query->get(['record_date', 'machine_id', 'quantity_produced', 'system_quantity', 'unit']);

        // Group by date
        $byDate = $records->groupBy(fn ($r) => $r->record_date->toDateString());

        $rows = [];
        for ($d = $start->copy(); $d->lte(now()); $d->addDay()) {
            $key = $d->toDateString();
            $group = $byDate->get($key, collect());

            $operator = (float) $group->sum('quantity_produced');
            $systemSum = $group->whereNotNull('system_quantity')->sum('system_quantity');
            $hasSystem = $group->whereNotNull('system_quantity')->count() > 0;

            $variance = null;
            if ($hasSystem && $systemSum > 0) {
                $variance = round((($operator - $systemSum) / $systemSum) * 100, 1);
            }

            $rows[] = [
                'date' => $key,
                'label' => $d->format('d M'),
                'operator' => round($operator, 2),
                'system' => $hasSystem ? round((float) $systemSum, 2) : null,
                'variance_pct' => $variance,  // positive = operator over-reports, negative = under-reports
                'has_system' => $hasSystem,
            ];
        }

        $hasSystemData = collect($rows)->contains('has_system', true);

        // Machines with records in the period (for filter dropdown)
        $machines = ProductionRecord::where('mine_area_id', $this->mineArea->id)
            ->where('team_id', $teamId)
            ->where('record_date', '>=', $start->toDateString())
            ->where('machine_id', '!=', null)
            ->with('machine:id,name')
            ->get(['machine_id'])
            ->pluck('machine')
            ->filter()
            ->unique('id')
            ->values();

        return [
            'has_system_data' => $hasSystemData,
            'rows' => $rows,
            'machines' => $machines,
        ];
    }
}
