<?php

namespace App\Traits;

use App\Models\Team;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Auth;

/**
 * Trait RealtimeUpdates
 *
 * Adds real-time update capabilities to Livewire components
 * Provides methods to initialize WebSocket listeners and handle real-time data
 */
trait RealtimeUpdates
{
    /**
     * Initialize real-time listeners for this component
     * Call this in the mount() or hydrate() method
     */
    public function initializeRealtimeUpdates(): void
    {
        $userId = Auth::id();
        $teamId = CurrentUser::get()?->current_team_id;

        // Dispatch JavaScript to initialize Reverb
        $this->dispatch('realtime:init', userId: $userId, teamId: $teamId);
    }

    /**
     * Get the current user
     */
    public function getCurrentUser(): ?User
    {
        $user = CurrentUser::get();

        return $user instanceof User ? $user : null;
    }

    /**
     * Get the current team
     */
    public function getCurrentTeam(): ?Team
    {
        return $this->getCurrentUser()?->currentTeam;
    }

    /**
     * Get user ID for Reverb subscriptions
     */
    public function getUserId(): string
    {
        return (string) Auth::id();
    }

    /**
     * Get team ID for Reverb subscriptions
     */
    public function getTeamId(): string
    {
        return (string) CurrentUser::get()?->current_team_id;
    }

    /**
     * Subscribe to machine location updates (for LiveMap component)
     */
    public function subscribeToMachineLocation(string $machineId): void
    {
        $this->dispatch('realtime:machine-location', machineId: $machineId);
    }

    /**
     * Subscribe to team-wide location updates
     */
    public function subscribeToTeamLocations(): void
    {
        $this->dispatch('realtime:team-locations');
    }

    /**
     * Subscribe to team alerts
     */
    public function subscribeToTeamAlerts(): void
    {
        $this->dispatch('realtime:team-alerts');
    }

    /**
     * Subscribe to predictive maintenance alerts
     */
    public function subscribeToMaintenanceAlerts(): void
    {
        $this->dispatch('realtime:maintenance-alerts');
    }

    /**
     * Subscribe to compliance violations
     */
    public function subscribeToComplianceViolations(): void
    {
        $this->dispatch('realtime:compliance-violations');
    }

    /**
     * Subscribe to geofence events
     */
    public function subscribeToGeofenceEvents(string $geofenceId): void
    {
        $this->dispatch('realtime:geofence-events', geofenceId: $geofenceId);
    }

    /**
     * Subscribe to machine status (online/offline)
     */
    public function subscribeToMachineStatus(string $machineId): void
    {
        $this->dispatch('realtime:machine-status', machineId: $machineId);
    }

    /**
     * Subscribe to presence (active team members)
     */
    public function subscribeToPresence(): void
    {
        $this->dispatch('realtime:presence');
    }

    /**
     * Stop listening to a specific channel
     */
    public function stopListeningToMachine(string $machineId): void
    {
        $this->dispatch('realtime:stop-machine', machineId: $machineId);
    }

    /**
     * Stop all listeners
     */
    public function stopAllListeners(): void
    {
        $this->dispatch('realtime:stop-all');
    }
}
