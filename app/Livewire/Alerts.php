<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Incident;
use App\Models\Machine;
use App\Models\MineArea;
use App\Traits\BrowserEventBridge;
use App\Traits\RealtimeUpdates;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Alerts extends Component
{
    use BrowserEventBridge, RealtimeUpdates, WithPagination;

    public string $search = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public string $selectedPriority = 'all';

    public string $selectedStatus = 'all';

    public string $selectedType = 'all';

    public bool $showDetailsModal = false;

    public ?int $selectedAlertId = null;

    public ?int $pendingDismissAlertId = null;

    public bool $showDismissConfirm = false;

    // Track when a dismissed-unresolved alert was created so UI can render specially
    /** @var array<int|string, mixed> */
    public array $recentlyDismissedUnresolved = [];

    // Tab navigation
    public string $activeTab = 'alerts'; // alerts | incidents

    // Incident report filters
    public string $incidentSearch = '';

    public string $incidentCategoryFilter = 'all';

    public string $incidentSeverityFilter = 'all';

    public string $incidentStatusFilter = 'all';

    // Log / Edit Incident modal
    public bool $showIncidentModal = false;

    public ?int $editingIncidentId = null;

    public string $incidentTitle = '';

    public string $incidentCategory = 'safety';

    public string $incidentSeverity = 'medium';

    public string $incidentMachineId = '';

    public string $incidentMineAreaId = '';

    public string $incidentDescription = '';

    public string $incidentOccurredAt = '';

    // Resolve modal
    public bool $showResolveModal = false;

    public ?int $resolvingIncidentId = null;

    public string $incidentResolutionNotes = '';

    /** @var array<string, string> */
    protected $alertPriorities = [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    /** @var array<string, string> */
    protected $alertTypes = [
        'temperature' => 'Temperature Warning',
        'fuel' => 'Fuel Level',
        'maintenance' => 'Maintenance Due',
        'sensor' => 'Sensor Fault',
        'geofence' => 'Geofence Breach',
        'downtime' => 'Extended Downtime',
        'speed_violation' => 'Speed Violation',
        'machine_idle' => 'Machine Idle',
    ];

    public function mount(): void
    {
        // Initialize real-time updates
        $this->initializeRealtimeUpdates();
        $this->subscribeToTeamAlerts();
    }

    public function getAlerts(): mixed
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            return new LengthAwarePaginator([], 0, 15);
        }

        return Alert::where('team_id', $team->id)
            ->when($this->search, function ($query) {
                $query->where('title', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            })
            ->when($this->selectedPriority !== 'all', function ($query) {
                $query->where('priority', $this->selectedPriority);
            })
            ->when($this->selectedStatus !== 'all', function ($query) {
                $query->where('status', $this->selectedStatus);
            })
            ->when($this->selectedType !== 'all', function ($query) {
                $query->where('type', $this->selectedType);
            })
            ->with('machine')
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);
    }

    public function setSortBy(string $column): void
    {
        $allowed = ['created_at', 'priority', 'status'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->find($alertId);

        if ($alert) {
            $this->authorize('acknowledge', $alert);
            $alert->update([
                'acknowledged_by' => Auth::id(),
                'acknowledged_at' => now(),
            ]);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Alert acknowledged']);
        }
    }

    public function resolveAlert(int $alertId): void
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->find($alertId);

        if ($alert) {
            $this->authorize('resolve', $alert);
            $alert->update([
                'status' => 'resolved',
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
            ]);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Alert resolved']);

            // If the resolved alert is currently selected in the details modal, close it
            if ($this->selectedAlertId === $alert->id) {
                $this->closeDetails();
            }

            // Refresh pagination/list to immediately remove the resolved alert from the current view
            $this->resetPage();

            // Emit event for any frontend listeners (optional)
            $this->dispatch('alert-resolved', ['id' => $alert->id]);
        }
    }

    public function dismissAlert(int $alertId): void
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->find($alertId);

        if ($alert) {
            // If the alert is not resolved, ask for confirmation before dismissing
            if ($alert->status !== 'resolved') {
                $this->pendingDismissAlertId = $alert->id;
                $this->showDismissConfirm = true;

                return;
            }

            $alert->update([
                'status' => 'dismissed',
                'dismissed_by' => Auth::id(),
                'dismissed_at' => now(),
            ]);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Alert dismissed']);
        }
    }

    public function confirmDismiss(string $choice = 'dismiss'): void
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->find($this->pendingDismissAlertId);

        if (! $alert) {
            $this->showDismissConfirm = false;
            $this->pendingDismissAlertId = null;

            return;
        }
        // If the alert is already resolved, allow normal dismissal which will remove it from active workflows
        if ($alert->status === 'resolved') {
            $alert->update([
                'status' => 'dismissed',
                'dismissed_by' => Auth::id(),
                'dismissed_at' => now(),
            ]);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Alert dismissed']);

            $this->showDismissConfirm = false;
            $this->pendingDismissAlertId = null;

            return;
        }

        // For unresolved alerts: if the user confirms the dismiss warning, mark it as dismissed_unresolved
        if ($choice === 'confirm') {
            $alert->update([
                'status' => 'dismissed_unresolved',
                'dismissed_by' => Auth::id(),
                'dismissed_at' => now(),
            ]);
            $this->dispatchBrowserEvent('notify', ['type' => 'warning', 'message' => 'Alert marked Dismissed - Unresolved']);
            $this->recentlyDismissedUnresolved[] = $alert->id;
        }

        $this->showDismissConfirm = false;
        $this->pendingDismissAlertId = null;
    }

    public function cancelDismiss(): void
    {
        $this->showDismissConfirm = false;
        $this->pendingDismissAlertId = null;
    }

    public function showDetails(int $alertId): void
    {
        $this->selectedAlertId = $alertId;
        $this->showDetailsModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailsModal = false;
        $this->selectedAlertId = null;
    }

    public function getSelectedAlert(): mixed
    {
        if ($this->selectedAlertId) {
            $team = Auth::user()->currentTeam;

            // Ensure selected alert belongs to the current team to avoid cross-team access
            $alert = Alert::where('team_id', $team->id)
                ->with(['machine', 'mineArea'])
                ->find($this->selectedAlertId);

            // If geofence id was stored in metadata, attach the geofence relation for convenience
            if ($alert && is_array($alert->metadata ?? [])) {
                $meta = $alert->metadata;
                $geofenceId = $meta['geofence_id'] ?? null;
                if ($geofenceId) {
                    $geofence = Geofence::where('team_id', $team->id)->find($geofenceId);
                    if ($geofence) {
                        $alert->setRelation('geofence', $geofence);
                    }
                }
            }

            return $alert;
        }

        return null;
    }

    /**
     * Return an array of mine-area managers (team users with manager-like roles)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMineAreaManagersForAlert(?Alert $alert): array
    {
        if ($alert === null || $alert->mineArea === null) {
            return [];
        }

        $team = Auth::user()->currentTeam;
        $candidates = $team->users()->with('roles')->get();

        $managers = $candidates->filter(function ($user) {
            $role = $user->roles->first()?->name ?? null;

            return in_array($role, ['admin', 'fleet_manager', 'manager']);
        })->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? '',
            ];
        })->values()->toArray();

        return $managers;
    }

    public function render(): View
    {
        $selected = $this->getSelectedAlert();

        return view('livewire.alerts', [
            'alerts' => $this->getAlerts(),
            'incidentReports' => $this->getIncidentReports(),
            'incidentCategories' => Incident::CATEGORIES,
            'incidentSeverities' => Incident::SEVERITIES,
            'incidentStatuses' => Incident::STATUSES,
            'formMachines' => $this->showIncidentModal ? $this->getMachinesForIncidentForm() : collect(),
            'formMineAreas' => $this->showIncidentModal ? $this->getMineAreasForIncidentForm() : collect(),
            'alertPriorities' => $this->alertPriorities,
            'alertTypes' => $this->alertTypes,
            'selectedAlert' => $selected,
            'mineAreaManagers' => $this->getMineAreaManagersForAlert($selected),
        ]);
    }

    public function getIncidentReports(): mixed
    {
        $team = Auth::user()->currentTeam;

        return Incident::where('team_id', $team->id)
            ->when($this->incidentSearch, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', "%{$this->incidentSearch}%")
                        ->orWhere('description', 'like', "%{$this->incidentSearch}%");
                });
            })
            ->when($this->incidentCategoryFilter !== 'all', fn ($q) => $q->where('category', $this->incidentCategoryFilter))
            ->when($this->incidentSeverityFilter !== 'all', fn ($q) => $q->where('severity', $this->incidentSeverityFilter))
            ->when($this->incidentStatusFilter !== 'all', fn ($q) => $q->where('status', $this->incidentStatusFilter))
            ->with(['machine:id,name', 'mineArea:id,name', 'reportedBy:id,name'])
            ->orderByDesc('occurred_at')
            ->paginate(15, ['*'], 'incidentPage');
    }

    // ── Incident CRUD ─────────────────────────────────────────────────────────

    public function openLogIncidentModal(?int $incidentId = null): void
    {
        $this->resetIncidentForm();
        if ($incidentId) {
            $team = Auth::user()->currentTeam;
            $incident = Incident::where('team_id', $team->id)->findOrFail($incidentId);
            $this->editingIncidentId = $incident->id;
            $this->incidentTitle = $incident->title;
            $this->incidentCategory = $incident->category;
            $this->incidentSeverity = $incident->severity;
            $this->incidentMachineId = (string) ($incident->machine_id ?? '');
            $this->incidentMineAreaId = (string) ($incident->mine_area_id ?? '');
            $this->incidentDescription = $incident->description;
            $this->incidentOccurredAt = $incident->occurred_at->format('Y-m-d\TH:i');
        } else {
            $this->incidentOccurredAt = now()->format('Y-m-d\TH:i');
        }
        $this->showIncidentModal = true;
    }

    public function closeIncidentModal(): void
    {
        $this->showIncidentModal = false;
        $this->resetIncidentForm();
    }

    public function saveIncident(): void
    {
        $data = $this->validate([
            'incidentTitle' => 'required|string|max:255',
            'incidentCategory' => 'required|in:'.implode(',', array_keys(Incident::CATEGORIES)),
            'incidentSeverity' => 'required|in:'.implode(',', array_keys(Incident::SEVERITIES)),
            'incidentDescription' => 'required|string|max:5000',
            'incidentOccurredAt' => 'required|date',
            'incidentMachineId' => 'nullable|integer',
            'incidentMineAreaId' => 'nullable|integer',
        ]);

        $team = Auth::user()->currentTeam;

        $payload = [
            'team_id' => $team->id,
            'reported_by' => Auth::id(),
            'title' => $data['incidentTitle'],
            'category' => $data['incidentCategory'],
            'severity' => $data['incidentSeverity'],
            'description' => $data['incidentDescription'],
            'occurred_at' => $data['incidentOccurredAt'],
            'machine_id' => $data['incidentMachineId'] ?: null,
            'mine_area_id' => $data['incidentMineAreaId'] ?: null,
        ];

        if ($this->editingIncidentId) {
            $incident = Incident::where('team_id', $team->id)->findOrFail($this->editingIncidentId);
            $incident->update($payload);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Incident updated']);
        } else {
            $payload['status'] = 'open';
            Incident::create($payload);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Incident logged']);
        }

        $this->closeIncidentModal();
        $this->resetPage('incidentPage');
    }

    public function openResolveModal(int $incidentId): void
    {
        $this->resolvingIncidentId = $incidentId;
        $this->incidentResolutionNotes = '';
        $this->showResolveModal = true;
    }

    public function closeResolveModal(): void
    {
        $this->showResolveModal = false;
        $this->resolvingIncidentId = null;
    }

    public function resolveIncident(): void
    {
        $team = Auth::user()->currentTeam;
        $incident = Incident::where('team_id', $team->id)->find($this->resolvingIncidentId);
        if ($incident) {
            $incident->update([
                'status' => 'resolved',
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
                'resolution_notes' => $this->incidentResolutionNotes ?: null,
            ]);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Incident marked resolved']);
        }
        $this->closeResolveModal();
    }

    public function updateIncidentStatus(int $incidentId, string $status): void
    {
        if (! in_array($status, array_keys(Incident::STATUSES))) {
            return;
        }
        $team = Auth::user()->currentTeam;
        $incident = Incident::where('team_id', $team->id)->find($incidentId);
        if ($incident) {
            $update = ['status' => $status];
            if ($status === 'resolved' && ! $incident->resolved_at) {
                $update['resolved_by'] = Auth::id();
                $update['resolved_at'] = now();
            }
            $incident->update($update);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Status updated']);
        }
    }

    private function resetIncidentForm(): void
    {
        $this->editingIncidentId = null;
        $this->incidentTitle = '';
        $this->incidentCategory = 'safety';
        $this->incidentSeverity = 'medium';
        $this->incidentMachineId = '';
        $this->incidentMineAreaId = '';
        $this->incidentDescription = '';
        $this->incidentOccurredAt = '';
    }

    /** @return Collection<int, Machine> */
    public function getMachinesForIncidentForm(): Collection
    {
        $team = Auth::user()->currentTeam;

        return Machine::where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name', 'machine_type']);
    }

    /** @return Collection<int, MineArea> */
    public function getMineAreasForIncidentForm(): Collection
    {
        $team = Auth::user()->currentTeam;

        return MineArea::where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
