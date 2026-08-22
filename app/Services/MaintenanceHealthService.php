<?php

namespace App\Services;

use App\Models\HealthMetric;
use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceHealthService
{
    /**
     * Update machine health status
     *
     * @param  array<string, mixed>  $data
     */
    public function updateHealthStatus(Machine $machine, array $data): MachineHealthStatus
    {
        $healthStatus = MachineHealthStatus::updateOrCreate(
            ['machine_id' => $machine->id],
            array_merge($data, ['team_id' => $machine->team_id])
        );

        // Recalculate overall score
        $healthStatus->overall_health_score = $healthStatus->calculateHealthScore();
        $healthStatus->health_status = $healthStatus->determineHealthStatus();
        $healthStatus->save();

        // Check for alerts
        $this->checkHealthAlerts($machine, $healthStatus);

        return $healthStatus;
    }

    /**
     * Check and create health alerts
     */
    protected function checkHealthAlerts(Machine $machine, MachineHealthStatus $healthStatus): void
    {
        // Critical health alert
        if ($healthStatus->overall_health_score < 40) {
            $this->createMaintenanceAlert($machine, [
                'alert_type' => 'health_critical',
                'title' => "Critical Health: {$machine->name}",
                'message' => "Machine health score is critically low at {$healthStatus->overall_health_score}%",
                'severity' => 'critical',
            ]);
        }
        // Warning health alert
        elseif ($healthStatus->overall_health_score < 70) {
            $this->createMaintenanceAlert($machine, [
                'alert_type' => 'health_warning',
                'title' => "Health Warning: {$machine->name}",
                'message' => "Machine health score is {$healthStatus->overall_health_score}%. Maintenance recommended.",
                'severity' => 'warning',
            ]);
        }

        // Fault code alert
        if ($healthStatus->fault_code_count > 0) {
            $this->createMaintenanceAlert($machine, [
                'alert_type' => 'fault_code',
                'title' => "Fault Codes Detected: {$machine->name}",
                'message' => "{$healthStatus->fault_code_count} active fault codes detected",
                'severity' => $healthStatus->fault_code_count > 3 ? 'critical' : 'warning',
            ]);
        }
    }

    /**
     * Create maintenance alert (avoid duplicates)
     *
     * @param  array<string, mixed>  $data
     */
    protected function createMaintenanceAlert(Machine $machine, array $data): ?MaintenanceAlert
    {
        // Check for existing active alert
        $existing = MaintenanceAlert::where('team_id', $machine->team_id)
            ->where('machine_id', $machine->id)
            ->where('alert_type', $data['alert_type'])
            ->where('status', 'active')
            ->where('triggered_at', '>=', now()->subHours(24))
            ->first();

        if ($existing) {
            return null;
        }

        $data['team_id'] = $machine->team_id;
        $data['machine_id'] = $machine->id;
        $data['triggered_at'] = now();
        $data['status'] = 'active';

        return MaintenanceAlert::create($data);
    }

    /**
     * Check and update maintenance schedules
     *
     * @return array<int,MaintenanceSchedule>
     */
    public function checkSchedules(Machine $machine): array
    {
        $schedules = MaintenanceSchedule::where('machine_id', $machine->id)
            ->where('status', 'active')
            ->get();

        $updates = [];

        foreach ($schedules as $schedule) {
            $oldStatus = $schedule->status;

            if ($schedule->isOverdue($machine)) {
                $schedule->status = 'overdue';
                $this->createMaintenanceAlert($machine, [
                    'maintenance_schedule_id' => $schedule->id,
                    'alert_type' => 'service_overdue',
                    'title' => "Overdue Maintenance: {$schedule->title}",
                    'message' => "Maintenance is overdue for {$machine->name}",
                    'severity' => 'critical',
                ]);
            } elseif ($schedule->isDue($machine)) {
                $schedule->status = 'due';
                $this->createMaintenanceAlert($machine, [
                    'maintenance_schedule_id' => $schedule->id,
                    'alert_type' => 'service_due',
                    'title' => "Maintenance Due: {$schedule->title}",
                    'message' => "Scheduled maintenance is due for {$machine->name}",
                    'severity' => 'warning',
                ]);
            }

            if ($oldStatus !== $schedule->status) {
                $schedule->save();
                $updates[] = $schedule;
            }
        }

        return $updates;
    }

    /**
     * Create maintenance record (work order)
     *
     * @param  array<string, mixed>  $data
     */
    public function createMaintenanceRecord(array $data): MaintenanceRecord
    {
        DB::beginTransaction();
        try {
            $record = MaintenanceRecord::create($data);

            // If linked to schedule, update schedule status
            if ($record->maintenance_schedule_id) {
                $schedule = MaintenanceSchedule::find($record->maintenance_schedule_id);
                if ($schedule) {
                    $schedule->status = 'completed';
                    $schedule->last_service_date = $record->completed_at ?? now();
                    $schedule->last_service_hours = $record->hour_meter_reading;
                    $schedule->last_service_km = $record->odometer_reading;

                    // Calculate next service
                    if ($schedule->schedule_type === 'hours' && ($schedule->interval_hours !== null && $schedule->interval_hours !== 0)) {
                        $schedule->next_service_hours = $record->hour_meter_reading + $schedule->interval_hours;
                    }
                    if ($schedule->schedule_type === 'kilometers' && ($schedule->interval_km !== null && $schedule->interval_km !== 0)) {
                        $schedule->next_service_km = $record->odometer_reading + $schedule->interval_km;
                    }
                    if ($schedule->schedule_type === 'calendar' && ($schedule->interval_days !== null && $schedule->interval_days !== 0)) {
                        $schedule->next_service_date = now()->addDays($schedule->interval_days);
                    }

                    $schedule->status = 'active';
                    $schedule->save();
                }
            }

            DB::commit();

            return $record;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Complete maintenance record
     *
     * @param  array<string, mixed>  $data
     */
    public function completeMaintenanceRecord(MaintenanceRecord $record, array $data): MaintenanceRecord
    {
        $record->update(array_merge($data, [
            'status' => 'completed',
            'completed_at' => now(),
        ]));

        // Update machine health if fault codes were cleared
        if (! empty($data['fault_codes_cleared'])) {
            $health = MachineHealthStatus::where('machine_id', $record->machine_id)->first();
            if ($health) {
                /** @var Collection<array-key, mixed> $clearedCodes */
                $clearedCodes = collect(is_array($data['fault_codes_cleared']) ? $data['fault_codes_cleared'] : []);
                /** @var Collection<array-key, mixed> $activeCodes */
                $activeCodes = collect(is_array($health->active_fault_codes ?? null) ? $health->active_fault_codes : []);

                $remainingCodes = $activeCodes->filter(function ($code) use ($clearedCodes) {
                    return ! $clearedCodes->contains($code);
                })->values()->toArray();

                $health->active_fault_codes = $remainingCodes;
                $health->fault_code_count = count($remainingCodes);
                $health->save();
            }
        }

        return $record;
    }

    /**
     * Get maintenance analytics
     *
     * @return array<string, mixed>
     */
    public function getMaintenanceAnalytics(int $teamId, Carbon $startDate, Carbon $endDate): array
    {
        // Completed maintenance
        $completedRecords = MaintenanceRecord::where('team_id', $teamId)
            ->completed()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();

        // Costs
        $totalCost = $completedRecords->sum('total_cost');
        $laborCost = $completedRecords->sum('labor_cost');
        $partsCost = $completedRecords->sum('parts_cost');

        // Time metrics
        $totalLaborHours = $completedRecords->sum('labor_hours');
        $avgRepairTime = $completedRecords->avg('duration');

        // By type
        $byType = $completedRecords->groupBy('maintenance_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_cost' => $group->sum('total_cost'),
                'total_hours' => $group->sum('labor_hours'),
            ];
        });

        // By machine
        $byMachine = $completedRecords->groupBy('machine_id')->map(function ($group) {
            $machine = $group->first()?->machine;

            return [
                'machine_id' => $machine?->id,
                'machine_name' => $machine?->name,
                'maintenance_count' => $group->count(),
                'total_cost' => $group->sum('total_cost'),
            ];
        })->sortByDesc('total_cost')->take(10)->values();

        // Health status overview
        $healthStatus = MachineHealthStatus::where('team_id', $teamId)
            ->select('health_status', DB::raw('COUNT(*) as count'))
            ->groupBy('health_status')
            ->get()
            ->pluck('count', 'health_status');

        // Active alerts
        $activeAlerts = MaintenanceAlert::where('team_id', $teamId)
            ->where('status', 'active')
            ->count();

        // Due/overdue schedules
        $dueSchedules = MaintenanceSchedule::where('team_id', $teamId)
            ->where('status', 'due')
            ->count();

        $overdueSchedules = MaintenanceSchedule::where('team_id', $teamId)
            ->where('status', 'overdue')
            ->count();

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_maintenance' => $completedRecords->count(),
                'total_cost' => round($totalCost, 2),
                'labor_cost' => round($laborCost, 2),
                'parts_cost' => round($partsCost, 2),
                'total_labor_hours' => round($totalLaborHours, 2),
                'avg_repair_time_hours' => round($avgRepairTime, 2),
            ],
            'by_type' => $byType,
            'top_machines_by_cost' => $byMachine,
            'health_overview' => $healthStatus,
            'alerts' => [
                'active' => $activeAlerts,
                'due_schedules' => $dueSchedules,
                'overdue_schedules' => $overdueSchedules,
            ],
        ];
    }

    /**
     * Get machine health report
     *
     * @return array<string, mixed>
     */
    public function getMachineHealthReport(Machine $machine): array
    {
        $health = MachineHealthStatus::where('machine_id', $machine->id)->first();

        if (! $health) {
            return [
                'machine_id' => $machine->id,
                'machine_name' => $machine->name,
                'health_status' => 'unknown',
                'overall_score' => null,
            ];
        }

        // Recent maintenance
        $recentMaintenance = MaintenanceRecord::where('machine_id', $machine->id)
            ->completed()
            ->latest('completed_at')
            ->limit(5)
            ->get();

        // Upcoming schedules
        $upcomingSchedules = MaintenanceSchedule::where('machine_id', $machine->id)
            ->whereIn('status', ['active', 'due', 'overdue'])
            ->orderBy('next_service_date')
            ->limit(5)
            ->get();

        // Active alerts
        $activeAlerts = MaintenanceAlert::where('machine_id', $machine->id)
            ->where('status', 'active')
            ->latest('triggered_at')
            ->get();

        // Recent metrics
        $recentMetrics = HealthMetric::where('machine_id', $machine->id)
            ->where('recorded_at', '>=', now()->subDays(7))
            ->latest('recorded_at')
            ->limit(20)
            ->get();

        return [
            'machine_id' => $machine->id,
            'machine_name' => $machine->name,
            'health_status' => $health->health_status,
            'overall_score' => $health->overall_health_score,
            'component_scores' => [
                'engine' => $health->engine_health,
                'transmission' => $health->transmission_health,
                'hydraulics' => $health->hydraulics_health,
                'electrical' => $health->electrical_health,
                'brakes' => $health->brakes_health,
                'cooling_system' => $health->cooling_system_health,
            ],
            'fault_codes' => [
                'count' => $health->fault_code_count,
                'codes' => $health->active_fault_codes ?? [],
            ],
            'recommendations' => $health->recommendations,
            'last_diagnostic' => $health->last_diagnostic_scan,
            'recent_maintenance' => $recentMaintenance,
            'upcoming_schedules' => $upcomingSchedules,
            'active_alerts' => $activeAlerts,
            'recent_metrics' => $recentMetrics,
        ];
    }
}
