<?php

namespace App\Livewire;

use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Services\AI\MaintenancePredictorAgent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class MaintenanceDashboard extends Component
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

    public string $selectedPeriod = 'month';

    public bool $showCriticalOnly = false;

    // Modal state
    public bool $showBookingModal = false;

    public ?int $editingScheduleId = null;

    // Form fields
    public string $machine_id = '';

    public string $maintenance_type = 'preventive';

    public string $title = '';

    public string $description = '';

    public string $scheduled_date = '';

    public int $estimated_duration_hours = 0;

    public int $estimated_cost = 0;

    public string $priority = 'medium';

    public string $required_parts = '';

    public string $technician_notes = '';

    public function openBookingModal(): void
    {
        $this->resetForm();
        $this->showBookingModal = true;
    }

    public function closeBookingModal(): void
    {
        $this->showBookingModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingScheduleId = null;
        $this->machine_id = '';
        $this->maintenance_type = 'preventive';
        $this->title = '';
        $this->description = '';
        $this->scheduled_date = '';
        $this->estimated_duration_hours = 0;
        $this->estimated_cost = 0;
        $this->priority = 'medium';
        $this->required_parts = '';
        $this->technician_notes = '';
    }

    public function bookMaintenance(): void
    {
        $this->validate([
            'machine_id' => 'required|exists:machines,id',
            'maintenance_type' => 'required|in:preventive,corrective,predictive,emergency,routine,inspection,calibration,overhaul,breakdown,seasonal',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'estimated_duration_hours' => 'nullable|numeric|min:0|max:1000',
            'estimated_cost' => 'nullable|numeric|min:0|max:99999999',
            'priority' => 'required|in:low,medium,high,critical',
            'required_parts' => 'nullable|string|max:1000',
            'technician_notes' => 'nullable|string|max:2000',
        ]);

        $user = auth()->user();
        $teamId = $user?->current_team_id;

        // Verify machine belongs to team
        $machine = Machine::where('id', $this->machine_id)
            ->where('team_id', $teamId)
            ->first();

        if (! $machine) {
            $this->dispatch('notify', ['message' => 'Invalid machine selected', 'type' => 'error']);

            return;
        }

        try {
            // Create maintenance record with sanitized inputs
            MaintenanceRecord::create([
                'team_id' => $teamId,
                'machine_id' => $this->machine_id,
                'maintenance_type' => $this->maintenance_type,
                'title' => strip_tags($this->title),
                'description' => strip_tags($this->description),
                'scheduled_date' => $this->scheduled_date,
                'status' => 'scheduled',
                'priority' => $this->priority,
                'labor_hours' => $this->estimated_duration_hours ?? 0,
                'labor_cost' => $this->estimated_cost ?? 0,
                'total_cost' => $this->estimated_cost ?? 0,
                'technician_notes' => strip_tags($this->technician_notes),
                'parts_used' => $this->required_parts ? ['notes' => strip_tags($this->required_parts)] : null,
            ]);

            Log::info('Maintenance scheduled', [
                'user_id' => $user?->id,
                'machine_id' => $this->machine_id,
                'type' => $this->maintenance_type,
            ]);

            $this->dispatch('notify', ['message' => 'Maintenance scheduled successfully', 'type' => 'success']);
            $this->closeBookingModal();

        } catch (\Exception $e) {
            Log::error('Failed to schedule maintenance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notify', ['message' => 'Failed to schedule maintenance', 'type' => 'error']);
        }
    }

    /**
     * @param  int|string  $recordId
     */
    public function completeScheduledMaintenance($recordId): void
    {
        if (! is_numeric($recordId)) {
            $this->dispatch('notify', ['message' => 'Invalid record ID', 'type' => 'error']);

            return;
        }

        $record = MaintenanceRecord::where('team_id', auth()->user()?->current_team_id)
            ->find($recordId);

        if (! $record) {
            $this->dispatch('notify', ['message' => 'Record not found or access denied', 'type' => 'error']);

            return;
        }

        try {
            $record->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => auth()->id(),
            ]);

            Log::info('Maintenance completed', [
                'user_id' => auth()->id(),
                'record_id' => $recordId,
            ]);

            $this->dispatch('notify', ['message' => 'Maintenance marked as completed', 'type' => 'success']);
        } catch (\Exception $e) {
            Log::error('Failed to complete maintenance', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notify', ['message' => 'Failed to update maintenance status', 'type' => 'error']);
        }
    }

    /**
     * @param  int|string  $recordId
     */
    public function cancelScheduledMaintenance($recordId): void
    {
        if (! is_numeric($recordId)) {
            $this->dispatch('notify', ['message' => 'Invalid record ID', 'type' => 'error']);

            return;
        }

        $record = MaintenanceRecord::where('team_id', auth()->user()?->current_team_id)
            ->find($recordId);

        if (! $record) {
            $this->dispatch('notify', ['message' => 'Record not found or access denied', 'type' => 'error']);

            return;
        }

        try {
            $record->update(['status' => 'cancelled']);

            Log::info('Maintenance cancelled', [
                'user_id' => auth()->id(),
                'record_id' => $recordId,
            ]);

            $this->dispatch('notify', ['message' => 'Maintenance cancelled', 'type' => 'info']);
        } catch (\Exception $e) {
            Log::error('Failed to cancel maintenance', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notify', ['message' => 'Failed to cancel maintenance', 'type' => 'error']);
        }
    }

    /**
     * Get delayed machines with reasons and color codes
     */
    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function getDelayedMachines(?int $teamId): Collection
    {
        if ($teamId === null) {
            return collect();
        }

        $delayedMachines = [];

        // Get all machines
        $machines = Machine::where('team_id', $teamId)
            ->with(['maintenanceRecords' => function ($query) {
                $query->whereIn('status', ['scheduled', 'in_progress'])
                    ->latest('created_at');
            }])
            ->get();

        foreach ($machines as $machine) {
            $delayInfo = $this->calculateMachineDelay($machine);

            if ($delayInfo['is_delayed']) {
                $delayedMachines[] = [
                    'machine' => $machine,
                    'delay_hours' => $delayInfo['delay_hours'],
                    'delay_reason' => $delayInfo['reason'],
                    'color_code' => $this->getDelayColorCode($delayInfo['delay_hours']),
                    'severity' => $this->getDelaySeverity($delayInfo['delay_hours']),
                    'expected_return' => $delayInfo['expected_return'],
                    'maintenance_type' => $delayInfo['maintenance_type'],
                ];
            }
        }

        // Sort by delay severity (longest delays first)
        usort($delayedMachines, function ($a, $b) {
            return $b['delay_hours'] <=> $a['delay_hours'];
        });

        return collect($delayedMachines);
    }

    /**
     * Calculate delay information for a machine
     */
    /**
     * @return array<string, mixed>
     */
    protected function calculateMachineDelay(Machine $machine): array
    {
        $delayInfo = [
            'is_delayed' => false,
            'delay_hours' => 0,
            'reason' => '',
            'expected_return' => null,
            'maintenance_type' => null,
        ];

        // Check if machine is not in active/production status
        if ($machine->status === 'maintenance') {
            // Get active maintenance records
            $activeMaintenance = $machine->maintenanceRecords
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->first();

            if ($activeMaintenance) {
                $startTime = $activeMaintenance->started_at ?? $activeMaintenance->scheduled_date;
                if ($startTime) {
                    $carbonStartTime = $startTime instanceof Carbon ? $startTime : Carbon::parse($startTime);
                    // Carbon 3: now()->diffInHours($past) is negative -- diff
                    // from the past timestamp forward for a positive elapsed.
                    $delayHours = (int) $carbonStartTime->diffInHours(now());
                    $estimatedHours = $activeMaintenance->labor_hours ?? 4;
                    // Consider delayed if beyond estimated duration
                    if ($delayHours > $estimatedHours) {
                        $delayInfo['is_delayed'] = true;
                        $delayInfo['delay_hours'] = $delayHours - $estimatedHours;
                        $delayInfo['reason'] = $activeMaintenance->title.' (Extended maintenance)';
                        $delayInfo['maintenance_type'] = $activeMaintenance->maintenance_type;
                        $delayInfo['expected_return'] = $carbonStartTime->copy()->addHours($estimatedHours);
                    }
                }
            } else {
                // In maintenance but no record found
                $delayInfo['is_delayed'] = true;
                $delayInfo['delay_hours'] = 24; // Default assumption
                $delayInfo['reason'] = 'In maintenance (No active work order)';
            }
        } elseif ($machine->status === 'idle') {
            // Check if idle for extended period
            if ($machine->updated_at && $machine->updated_at->diffInHours(now()) > 8) {
                $delayInfo['is_delayed'] = true;
                $delayInfo['delay_hours'] = (int) $machine->updated_at->diffInHours(now());
                $delayInfo['reason'] = 'Idle - Not assigned to production';
            }
        } elseif ($machine->status === 'offline') {
            // Offline machines are definitely delayed
            $delayInfo['is_delayed'] = true;
            $delayInfo['delay_hours'] = $machine->updated_at ? (int) $machine->updated_at->diffInHours(now()) : 48;
            $delayInfo['reason'] = 'Offline - System unavailable';
        }

        // Check for overdue scheduled maintenance causing delays
        $overdueMaintenance = MaintenanceRecord::where('machine_id', $machine->id)
            ->where('team_id', $machine->team_id)
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<', now()->subHours(2))
            ->first();

        if ($overdueMaintenance && ! $delayInfo['is_delayed']) {
            $delayInfo['is_delayed'] = true;
            $delayInfo['delay_hours'] = (int) $overdueMaintenance->scheduled_date->diffInHours(now());
            $delayInfo['reason'] = 'Waiting for scheduled maintenance: '.$overdueMaintenance->title;
            $delayInfo['maintenance_type'] = $overdueMaintenance->maintenance_type;
        }

        return $delayInfo;
    }

    /**
     * Get color code based on delay duration
     */
    protected function getDelayColorCode(int|float $hours): string
    {
        if ($hours >= 48) {
            return 'red'; // Critical - 2+ days
        } elseif ($hours >= 24) {
            return 'orange'; // Severe - 1-2 days
        } elseif ($hours >= 12) {
            return 'yellow'; // Warning - 12-24 hours
        } else {
            return 'blue'; // Minor - less than 12 hours
        }
    }

    /**
     * Get delay severity label
     */
    protected function getDelaySeverity(int|float $hours): string
    {
        if ($hours >= 48) {
            return 'Critical';
        } elseif ($hours >= 24) {
            return 'Severe';
        } elseif ($hours >= 12) {
            return 'Warning';
        } else {
            return 'Minor';
        }
    }

    public function render(): View
    {
        $teamId = auth()->user()?->current_team_id;

        // Get date range
        $dateRange = $this->getDateRange();

        // Health overview
        $healthStatuses = MachineHealthStatus::where('team_id', $teamId)
            ->with('machine')
            ->when($this->showCriticalOnly, fn ($q): mixed => $q->critical())
            ->latest('updated_at')
            ->get();

        $healthStats = [
            'total_machines' => Machine::where('team_id', $teamId)->count(),
            // Machines that actually have a health assessment -- the blade
            // shows an honest "no assessments yet" state instead of a
            // fabricated 0% average when this is zero.
            'assessed_machines' => $healthStatuses->count(),
            'excellent' => $healthStatuses->where('health_status', 'excellent')->count(),
            'good' => $healthStatuses->where('health_status', 'good')->count(),
            'fair' => $healthStatuses->where('health_status', 'fair')->count(),
            'poor' => $healthStatuses->where('health_status', 'poor')->count(),
            'critical' => $healthStatuses->where('health_status', 'critical')->count(),
            'avg_health_score' => round($healthStatuses->avg('overall_health_score') ?? 0, 1),
        ];

        // Maintenance schedules
        $dueSchedules = MaintenanceSchedule::where('team_id', $teamId)
            ->with('machine')
            ->due()
            ->orderBy('next_service_date')
            ->limit(10)
            ->get();

        $overdueSchedules = MaintenanceSchedule::where('team_id', $teamId)
            ->with('machine')
            ->overdue()
            ->orderBy('next_service_date')
            ->limit(10)
            ->get();

        // Recent maintenance records
        $recentMaintenance = MaintenanceRecord::where('team_id', $teamId)
            ->with(['machine', 'assignedTo', 'completedBy'])
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->latest('created_at')
            ->limit(10)
            ->get();

        // Maintenance statistics
        $maintenanceStats = [
            'total_completed' => MaintenanceRecord::where('team_id', $teamId)
                ->whereBetween('completed_at', [$dateRange['start'], $dateRange['end']])
                ->completed()
                ->count(),
            'in_progress' => MaintenanceRecord::where('team_id', $teamId)
                ->inProgress()
                ->count(),
            'total_cost' => MaintenanceRecord::where('team_id', $teamId)
                ->whereBetween('completed_at', [$dateRange['start'], $dateRange['end']])
                ->sum('total_cost'),
            'avg_repair_time' => MaintenanceRecord::where('team_id', $teamId)
                ->whereBetween('completed_at', [$dateRange['start'], $dateRange['end']])
                ->completed()
                ->avg('labor_hours'),
        ];

        // Active alerts
        $activeAlerts = MaintenanceAlert::where('team_id', $teamId)
            ->with(['machine', 'maintenanceSchedule'])
            ->active()
            ->orderByDesc('severity')
            ->latest('triggered_at')
            ->limit(10)
            ->get();

        // Machines needing attention
        $machinesNeedingAttention = MachineHealthStatus::where('team_id', $teamId)
            ->with('machine')
            ->needsAttention()
            ->orderBy('overall_health_score')
            ->limit(10)
            ->get();

        // Get all machines for booking dropdown
        $machines = Machine::where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        // Get scheduled maintenance (planned)
        $scheduledMaintenance = MaintenanceRecord::where('team_id', $teamId)
            ->with('machine')
            ->where('status', 'scheduled')
            ->orderBy('scheduled_date')
            ->get();

        // Get in-progress maintenance (actual)
        $inProgressMaintenance = MaintenanceRecord::where('team_id', $teamId)
            ->with('machine')
            ->where('status', 'in_progress')
            ->orderBy('started_at')
            ->get();

        // Get delayed machines
        $delayedMachines = $this->getDelayedMachines($teamId);

        // Calculate delay statistics
        $delayStats = [
            'total_delayed' => $delayedMachines->count(),
            'critical' => $delayedMachines->where('severity', 'Critical')->count(),
            'severe' => $delayedMachines->where('severity', 'Severe')->count(),
            'avg_delay_hours' => $delayedMachines->avg('delay_hours'),
            'total_lost_hours' => $delayedMachines->sum('delay_hours'),
        ];

        // Get AI-powered maintenance predictions
        $aiRecommendations = collect();
        $aiInsights = collect();
        $currentTeam = auth()->user()?->currentTeam;
        if ($currentTeam !== null) {
            $aiAnalysis = (new MaintenancePredictorAgent)->analyze($currentTeam);
            $recommendations = $aiAnalysis['recommendations'] ?? [];
            $insights = $aiAnalysis['insights'] ?? [];
            $aiRecommendations = collect(is_array($recommendations) ? $recommendations : [])->take(5);
            $aiInsights = collect(is_array($insights) ? $insights : [])->take(3);
        }

        return view('livewire.maintenance-dashboard', [
            // Costs follow the team's Settings currency, same as Fuel
            // Management -- maintenance was the one money surface still
            // hardcoding "R" regardless of the setting.
            'teamCurrency' => auth()->user()->currentTeam->currency ?? 'ZAR',
            'healthStatuses' => $healthStatuses,
            'healthStats' => $healthStats,
            'dueSchedules' => $dueSchedules,
            'overdueSchedules' => $overdueSchedules,
            'recentMaintenance' => $recentMaintenance,
            'maintenanceStats' => $maintenanceStats,
            'activeAlerts' => $activeAlerts,
            'machinesNeedingAttention' => $machinesNeedingAttention,
            'machines' => $machines,
            'scheduledMaintenance' => $scheduledMaintenance,
            'inProgressMaintenance' => $inProgressMaintenance,
            'delayedMachines' => $delayedMachines,
            'delayStats' => $delayStats,
            'aiRecommendations' => $aiRecommendations,
            'aiInsights' => $aiInsights,
        ]);
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    protected function getDateRange(): array
    {
        return match ($this->selectedPeriod) {
            'today' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'week' => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'quarter' => ['start' => now()->startOfQuarter(), 'end' => now()->endOfQuarter()],
            'year' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            default => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
        };
    }
}
