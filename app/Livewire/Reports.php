<?php

namespace App\Livewire;

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Report;
use App\Traits\BrowserEventBridge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class Reports extends Component
{
    use BrowserEventBridge;
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public string $selectedType = 'all';

    public string $selectedStatus = 'all';

    public string $selectedMineAreaId = '';

    public string $selectedGeofenceId = '';

    public string $selectedMachineId = '';

    /** @var Collection<int, mixed>|null */
    public ?Collection $machinesList = null;

    public bool $showDeleteConfirm = false;

    public ?int $deleteReportId = null;

    /** @var array<string, string> */
    protected array $reportTypes = [
        'production' => 'Production Summary',
        'fleet_utilization' => 'Fleet Utilization',
        'maintenance_schedule' => 'Maintenance Schedule',
        'fuel_consumption' => 'Fuel Consumption',
        'material_tracking' => 'Material Tracking',
        'downtime_analysis' => 'Downtime Analysis',
    ];

    public function mount()
    {
        $this->sortBy = 'created_at';
        $this->sortDirection = 'desc';
        $this->selectedMineAreaId = '';
        $this->selectedGeofenceId = '';
        $this->selectedMachineId = '';
    }

    public function getReports()
    {
        $team = Auth::user()->currentTeam;

        if (! $team) {
            return collect();
        }

        $searchTerm = trim($this->search);

        return Report::where('team_id', $team->id)
            ->when($searchTerm, function ($query) use ($searchTerm) {
                $query->where('title', 'like', '%'.$searchTerm.'%')
                    ->orWhere('description', 'like', '%'.$searchTerm.'%');
            })
            ->when($this->selectedMineAreaId, function ($query) {
                $query->where('filters->mine_area_id', $this->selectedMineAreaId);
            })
            ->when($this->selectedGeofenceId, function ($query) {
                $query->where('filters->geofence_id', $this->selectedGeofenceId);
            })
            ->when($this->selectedMachineId, function ($query) {
                $query->where('filters->machine_id', $this->selectedMachineId);
            })
            ->when($this->selectedType !== 'all', function ($query) {
                $query->where('type', $this->selectedType);
            })
            ->when($this->selectedStatus !== 'all', function ($query) {
                $query->where('status', $this->selectedStatus);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    public function setSortBy($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function deleteReport($reportId)
    {
        // Validate report ID
        if (! is_numeric($reportId)) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Invalid report ID']);

            return;
        }

        $team = Auth::user()->currentTeam;
        $report = Report::where('team_id', $team->id)->find($reportId);

        if (! $report) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Report not found or access denied']);
            $this->showDeleteConfirm = false;

            return;
        }

        try {
            $this->authorize('delete', $report);

            // Delete associated files if they exist
            if ($report->file_path && Storage::exists($report->file_path)) {
                Storage::delete($report->file_path);
            }

            $report->delete();

            Log::info('User deleted report', [
                'user_id' => Auth::id(),
                'report_id' => $reportId,
                'report_type' => $report->type,
            ]);

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Report deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete report', [
                'user_id' => Auth::id(),
                'report_id' => $reportId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Failed to delete report']);
        }

        $this->showDeleteConfirm = false;
        $this->deleteReportId = null;
    }

    public function confirmDelete($reportId)
    {
        $this->deleteReportId = $reportId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteConfirm = false;
        $this->deleteReportId = null;
    }

    public function downloadReport($reportId)
    {
        // Validate report ID
        if (! is_numeric($reportId)) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Invalid report ID']);

            return;
        }

        $team = Auth::user()->currentTeam;
        $report = Report::where('team_id', $team->id)->find($reportId);

        if (! $report) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Report not found or access denied']);

            return;
        }

        $this->authorize('download', $report);

        if ($report->status !== 'completed') {
            $this->dispatchBrowserEvent('notify', ['type' => 'warning', 'message' => 'Report is not ready for download']);

            return;
        }

        // Prevent path traversal attacks
        if ($report->file_path && ! str_contains($report->file_path, '..')) {
            if (Storage::exists($report->file_path)) {
                Log::info('User downloaded report', [
                    'user_id' => Auth::id(),
                    'report_id' => $reportId,
                ]);

                return Storage::download($report->file_path, $report->title.'.'.$report->format);
            }
        }

        $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Report file not found']);
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $mineAreas = $team ? MineArea::where('team_id', $team->id)->get() : collect();
        $geofences = $team ? Geofence::where('team_id', $team->id)->get() : collect();
        $this->machinesList = $team ? Machine::where('team_id', $team->id)->select('id', 'name')->get() : collect();

        return view('livewire.reports', [
            'reports' => $this->getReports(),
            'reportTypes' => $this->reportTypes,
            'mineAreas' => $mineAreas,
            'geofences' => $geofences,
            'machinesList' => $this->machinesList,
        ]);
    }
}
