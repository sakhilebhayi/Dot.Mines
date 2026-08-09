<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Geofence;
use App\Traits\BrowserEventBridge;
use App\Traits\RealtimeUpdates;
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
    public array $recentlyDismissedUnresolved = [];

    /** @var array<string, string> */
    protected array $alertPriorities = [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    /** @var array<string, string> */
    protected array $alertTypes = [
        'temperature' => 'Temperature Warning',
        'fuel' => 'Fuel Level',
        'maintenance' => 'Maintenance Due',
        'sensor' => 'Sensor Fault',
        'geofence' => 'Geofence Breach',
        'downtime' => 'Extended Downtime',
        'speed_violation' => 'Speed Violation',
        'machine_idle' => 'Machine Idle',
        'fatigue' => 'Operator Fatigue',
    ];

    public function mount(): void
    {
        // Initialize real-time updates
        $this->initializeRealtimeUpdates();
        $this->subscribeToTeamAlerts();
    }

    public function getAlerts()
    {
        $team = Auth::user()->currentTeam;

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

    public function setSortBy($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function acknowledgeAlert($alertId)
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->find($alertId);

        if ($alert) {
            $this->authorize('acknowledge', $alert);

            $alert->update([
                'status' => 'acknowledged',
                'acknowledged_by' => Auth::id(),
                'acknowledged_at' => now(),
            ]);
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Alert acknowledged']);
        }
    }

    public function resolveAlert($alertId)
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

    public function dismissAlert($alertId)
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->find($alertId);

        if ($alert) {
            $this->authorize('update', $alert);

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

    public function confirmDismiss($choice = 'dismiss')
    {
        $team = Auth::user()->currentTeam;
        $alert = Alert::where('team_id', $team->id)->find($this->pendingDismissAlertId);

        if (! $alert) {
            $this->showDismissConfirm = false;
            $this->pendingDismissAlertId = null;

            return;
        }

        $this->authorize('update', $alert);

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

    public function cancelDismiss()
    {
        $this->showDismissConfirm = false;
        $this->pendingDismissAlertId = null;
    }

    public function showDetails($alertId)
    {
        $this->selectedAlertId = $alertId;
        $this->showDetailsModal = true;
    }

    public function closeDetails()
    {
        $this->showDetailsModal = false;
        $this->selectedAlertId = null;
    }

    public function getSelectedAlert()
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
     */
    public function getMineAreaManagersForAlert($alert): array
    {
        if (! $alert || ! $alert->mineArea) {
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

    public function render()
    {
        $selected = $this->getSelectedAlert();

        return view('livewire.alerts', [
            'alerts' => $this->getAlerts(),
            'alertPriorities' => $this->alertPriorities,
            'alertTypes' => $this->alertTypes,
            'selectedAlert' => $selected,
            'mineAreaManagers' => $this->getMineAreaManagersForAlert($selected),
        ]);
    }
}
