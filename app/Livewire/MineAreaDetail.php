<?php

namespace App\Livewire;

use App\Actions\MineAreas\AssignMachineToArea;
use App\Actions\MineAreas\UnassignMachineFromArea;
use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Models\MinePlanUpload;
use App\Models\ProductionRecord;
use App\Models\ProductionTarget;
use App\Models\User;
use App\Services\FileUploadService;
use App\Support\ApiPayload;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * @psalm-suppress MissingConstructor -- Livewire injects $mineArea via mount()
 */
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

    public ?float $targetQuantity = null;

    public string $productionUnit = 'tonnes';

    public ?int $productionMachineId = null;

    public string $productionNotes = '';

    public string $productionPeriod = 'week'; // week, month, quarter

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

    /**
     * @return array<string, string>
     */
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
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
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
        $this->authorize('update', $this->mineArea);

        $this->validate([
            'selectedMachineId' => 'required|exists:machines,id',
        ]);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $machine = Machine::query()->where('team_id', $team->id)->findOrFail($this->selectedMachineId);

        app(AssignMachineToArea::class)->execute(
            $team,
            $this->mineArea,
            $machine,
            (int) Auth::id(),
            $this->assignmentReason ?: null,
        );

        $this->closeAssignModal();
        $this->dispatch('notify', ['message' => "{$machine->name} assigned to {$this->mineArea->name}", 'type' => 'success']);
    }

    public function unassignMachine(int $machineId): void
    {
        $this->authorize('update', $this->mineArea);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $machine = Machine::query()->where('team_id', $team->id)->findOrFail($machineId);

        $newArea = app(UnassignMachineFromArea::class)->execute($team, $this->mineArea, $machine, (int) Auth::id());

        if ($newArea === null) {
            // Machine::mine_area_id is NOT NULL -- refusing preserves the invariant.
            $this->dispatch('notify', ['message' => "Cannot unassign {$machine->name}; at least one active mine area must be set. Assign to another area first.", 'type' => 'error']);

            return;
        }

        $this->dispatch('notify', ['message' => "{$machine->name} reassigned to {$newArea->name} (cannot leave unassigned)", 'type' => 'success']);
    }

    // === PRODUCTION TRACKING ===

    public function openProductionModal(): void
    {
        $this->showProductionModal = true;
        $this->productionDate = now()->toDateString();
        $this->quantityProduced = null;
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
        $this->authorize('update', $this->mineArea);

        $this->validate([
            'productionDate' => 'required|date',
            'quantityProduced' => 'required|numeric|min:0',
            'productionShift' => 'required|in:day,night,continuous',
        ]);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

        ProductionRecord::create([
            'team_id' => $team->id,
            'mine_area_id' => $this->mineArea->id,
            'machine_id' => $this->productionMachineId !== null && $this->productionMachineId !== 0 ? $this->productionMachineId : null,
            'record_date' => $this->productionDate,
            'shift' => $this->productionShift,
            'quantity_produced' => $this->quantityProduced,
            'target_quantity' => $this->targetQuantity,
            'unit' => $this->productionUnit,
            'notes' => $this->productionNotes ?: null,
            'status' => 'completed',
        ]);

        $this->closeProductionModal();
        $this->dispatch('notify', ['message' => 'Production record saved successfully', 'type' => 'success']);
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
        $this->authorize('update', $this->mineArea);

        $this->validate([
            'targetStartDate' => 'required|date',
            'targetEndDate' => 'required|date|after_or_equal:targetStartDate',
            'targetValue' => 'required|numeric|min:0',
        ]);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

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
        $this->dispatch('notify', ['message' => 'Production target created successfully', 'type' => 'success']);
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
        $this->authorize('update', $this->mineArea);

        $this->validate([
            'planTitle' => 'required|string|max:255',
            'planFile' => 'required|file|max:51200',
        ]);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

        $file = $this->planFile;

        if ($file === null) {
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
            $this->dispatch('notify', ['message' => 'Mine plan uploaded successfully', 'type' => 'success']);

        } catch (\Throwable $e) {
            Log::error('Failed to upload mine plan', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['message' => "We couldn't upload that file. Check that it's a supported format and under the size limit, then try again.", 'type' => 'error']);
        }
    }

    public function deleteMinePlan(int $planId): void
    {
        $this->authorize('delete', $this->mineArea);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $plan = MinePlanUpload::where('team_id', $team->id)->findOrFail($planId);
        $disk = ApiPayload::str(data_get($plan->metadata, 'disk'), 'public');
        Storage::disk($disk)->delete($plan->file_path ?? '');
        $plan->delete();

        $this->dispatch('notify', ['message' => 'Mine plan deleted', 'type' => 'success']);
    }

    public function activateMinePlan(int $planId): void
    {
        $this->authorize('update', $this->mineArea);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $plan = MinePlanUpload::where('team_id', $team->id)->findOrFail($planId);
        $plan->update(['status' => 'active']);

        $this->dispatch('notify', ['message' => 'Mine plan activated', 'type' => 'success']);
    }

    public function archiveMinePlan(int $planId): void
    {
        $this->authorize('update', $this->mineArea);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $plan = MinePlanUpload::where('team_id', $team->id)->findOrFail($planId);
        $plan->update(['status' => 'archived']);

        $this->dispatch('notify', ['message' => 'Mine plan archived', 'type' => 'success']);
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
        $this->authorize('create', Alert::class);

        $this->validate([
            'alertTitle' => 'required|string|max:255',
            'alertPriority' => 'required|in:critical,high,medium,low',
        ]);

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

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
        $this->dispatch('notify', ['message' => 'Area alert created', 'type' => 'success']);
    }

    public function acknowledgeAlert(int $alertId): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $alert = Alert::where('team_id', $team->id)->findOrFail($alertId);
        $this->authorize('acknowledge', $alert);
        $alert->acknowledge(CurrentUser::get()?->id);

        $this->dispatch('notify', ['message' => 'Alert acknowledged', 'type' => 'success']);
    }

    public function resolveAlert(int $alertId): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $alert = Alert::where('team_id', $team->id)->findOrFail($alertId);
        $this->authorize('resolve', $alert);
        $alert->resolve(CurrentUser::get()?->id);

        $this->dispatch('notify', ['message' => 'Alert resolved', 'type' => 'success']);
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

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $geofence = Geofence::where('team_id', $team->id)->findOrFail($this->selectedGeofenceId);
        $this->authorize('update', $geofence);
        $geofence->update(['mine_area_id' => $this->mineArea->id]);

        $this->closeGeofenceModal();
        $this->dispatch('notify', ['message' => "{$geofence->name} linked to {$this->mineArea->name}", 'type' => 'success']);
    }

    public function unlinkGeofence(int $geofenceId): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $geofence = Geofence::where('team_id', $team->id)->findOrFail($geofenceId);
        $this->authorize('update', $geofence);
        $geofence->update(['mine_area_id' => null]);

        $this->dispatch('notify', ['message' => "{$geofence->name} unlinked from area", 'type' => 'success']);
    }

    // === RENDER ===

    public function render(): View
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

        // Refresh mine area with counts
        $this->mineArea->loadCount(['machines', 'geofences', 'minePlanUploads', 'productionRecords']);

        // Assigned machines
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
        ]);
    }

    /**
     * @return array<string, mixed>
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

        $targetValue = $monthTarget !== null ? (float) $monthTarget->target_quantity : 0.0;
        $targetProgress = $targetValue > 0.0 ? round(((float) $monthProduction / $targetValue) * 100.0, 1) : 0.0;

        return [
            'today' => $todayProduction,
            'week' => $weekProduction,
            'month' => $monthProduction,
            'target' => $targetValue,
            'target_progress' => min($targetProgress, 100),
            'target_unit' => $monthTarget?->unit ?? 'tonnes',
        ];
    }
}
