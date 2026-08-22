<?php

namespace App\Services\Reports;

use App\Models\ComplianceViolation;
use App\Models\FuelTransaction;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\ProductionRecord;
use App\Models\Report;
use Carbon\Carbon;

/**
 * Builds the real, queried data behind each report type. Every method
 * returns the same shape -- ['headers' => [...], 'rows' => [[...]], 'summary'
 * => [...]] -- so the format writers (CSV/XLSX/PDF) don't need to know
 * anything about the underlying report type.
 */
class ReportDataService
{
    /**
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    public function build(Report $report): array
    {
        $filters = $report->filters ?? [];
        $team = $report->team;

        if ($team === null) {
            throw new \InvalidArgumentException('Report has no owning team.');
        }

        $start = Carbon::parse($filters['start_date'] ?? now()->subDays(30))->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? now())->endOfDay();
        $machineIds = $filters['machine_ids'] ?? [];
        $geofenceIds = $filters['geofence_ids'] ?? [];

        return match ($report->type) {
            'production' => $this->production($team->id, $start, $end, $machineIds),
            'fleet_utilization' => $this->fleetUtilization($team->id, $start, $end, $machineIds),
            'maintenance_schedule' => $this->maintenanceSchedule($team->id, $start, $end, $machineIds),
            'fuel_consumption' => $this->fuelConsumption($team->id, $start, $end, $machineIds),
            'material_tracking' => $this->materialTracking($team->id, $start, $end, $geofenceIds),
            'downtime_analysis' => $this->downtimeAnalysis($team->id, $start, $end, $machineIds),
            'compliance' => $this->compliance($team->id, $start, $end),
            default => throw new \InvalidArgumentException("Unsupported report type: {$report->type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $machineIds
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    private function production(int $teamId, Carbon $start, Carbon $end, array $machineIds): array
    {
        // whereDate bounds, not whereBetween on date strings: the model's
        // 'date' cast stores record_date with a time component, so a string
        // BETWEEN comparison silently excluded every record on the report's
        // end date ('Y-m-d H:i:s' sorts after 'Y-m-d').
        $query = ProductionRecord::where('team_id', $teamId)
            ->whereDate('record_date', '>=', $start)
            ->whereDate('record_date', '<=', $end)
            ->with(['machine', 'mineArea']);

        if (! empty($machineIds)) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->orderBy('record_date')->get();

        $rows = $records->map(fn ($r) => [
            $r->record_date?->format('Y-m-d'),
            $r->mineArea?->name ?? '—',
            $r->machine?->name ?? '—',
            ucfirst($r->shift ?? '—'),
            (float) $r->quantity_produced,
            $r->target_quantity !== null ? (float) $r->target_quantity : null,
            $r->unit,
        ])->all();

        $totalProduced = $records->sum('quantity_produced');
        $totalTarget = $records->sum('target_quantity');

        return [
            'headers' => ['Date', 'Mine Area', 'Machine', 'Shift', 'Quantity Produced', 'Target', 'Unit'],
            'rows' => $rows,
            'summary' => [
                'Records' => $records->count(),
                'Total Produced' => round($totalProduced, 2),
                'Total Target' => round($totalTarget, 2),
                'Achievement' => $totalTarget > 0 ? round(($totalProduced / $totalTarget) * 100, 1).'%' : 'N/A',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $machineIds
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    private function fleetUtilization(int $teamId, Carbon $start, Carbon $end, array $machineIds): array
    {
        $query = Machine::where('team_id', $teamId);
        if (! empty($machineIds)) {
            $query->whereIn('id', $machineIds);
        }
        $machines = $query->get();

        $rows = [];
        $totalHours = 0.0;
        foreach ($machines as $machine) {
            $metrics = $machine->metrics()
                ->whereBetween('recorded_at', [$start, $end])
                ->get();

            // operating_hours is a cumulative engine-hours counter (the same
            // semantics MachinePerformanceService::dayDelta() and the Bell
            // production derivation rely on). Hours worked in the range is
            // therefore the counter's DELTA across the window -- summing
            // meter readings multiplied the machine's lifetime hours by the
            // number of readings and produced utilization percentages in the
            // thousands.
            $readings = $metrics->pluck('operating_hours')
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => (float) $value);

            $hoursInRange = $readings->count() >= 2
                ? max(0.0, $readings->max() - $readings->min())
                : null;

            // Whole inclusive calendar days: Carbon 3's diffInDays returns a
            // float (0.999... for a same-day 00:00->23:59 range), so cast
            // before the +1 or the availability denominator doubles.
            $daysInRange = max(1, (int) $start->diffInDays($end) + 1);
            $utilizationPercent = $hoursInRange !== null
                ? round(($hoursInRange / ($daysInRange * 24)) * 100, 1)
                : null;
            $totalHours += $hoursInRange ?? 0.0;

            $rows[] = [
                $machine->name,
                $machine->machine_type,
                ucfirst($machine->status ?? '—'),
                $hoursInRange !== null ? round($hoursInRange, 2) : 'Insufficient data',
                $metrics->count(),
                $utilizationPercent !== null ? $utilizationPercent.'%' : '—',
            ];
        }

        return [
            'headers' => ['Machine', 'Type', 'Status', 'Operating Hours', 'Readings', 'Utilization'],
            'rows' => $rows,
            'summary' => [
                'Machines' => $machines->count(),
                'Total Operating Hours' => round($totalHours, 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $machineIds
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    private function maintenanceSchedule(int $teamId, Carbon $start, Carbon $end, array $machineIds): array
    {
        $query = MaintenanceRecord::where('team_id', $teamId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('scheduled_date', [$start, $end])
                    ->orWhereBetween('completed_at', [$start, $end]);
            })
            ->with('machine');

        if (! empty($machineIds)) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->orderBy('scheduled_date')->get();

        $rows = $records->map(fn ($r) => [
            $r->work_order_number,
            $r->machine?->name ?? '—',
            $r->title,
            ucfirst($r->maintenance_type ?? '—'),
            ucfirst($r->status),
            $r->scheduled_date?->format('Y-m-d'),
            $r->completed_at?->format('Y-m-d'),
            $r->total_cost !== null ? (float) $r->total_cost : null,
        ])->all();

        return [
            'headers' => ['Work Order', 'Machine', 'Title', 'Type', 'Status', 'Scheduled', 'Completed', 'Cost'],
            'rows' => $rows,
            'summary' => [
                'Records' => $records->count(),
                'Completed' => $records->where('status', 'completed')->count(),
                'Overdue/Open' => $records->whereIn('status', ['scheduled', 'in_progress'])->count(),
                'Total Cost' => round($records->sum('total_cost'), 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $machineIds
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    private function fuelConsumption(int $teamId, Carbon $start, Carbon $end, array $machineIds): array
    {
        $query = FuelTransaction::where('team_id', $teamId)
            ->whereBetween('transaction_date', [$start, $end])
            ->with('machine');

        if (! empty($machineIds)) {
            $query->whereIn('machine_id', $machineIds);
        }

        $transactions = $query->orderBy('transaction_date')->get();

        $rows = $transactions->map(fn ($t) => [
            $t->transaction_date?->format('Y-m-d'),
            $t->machine?->name ?? '—',
            ucfirst($t->transaction_type),
            (float) $t->quantity_liters,
            $t->unit_price !== null ? (float) $t->unit_price : null,
            $t->total_cost !== null ? (float) $t->total_cost : null,
        ])->all();

        $dispensing = $transactions->where('transaction_type', 'dispensing');

        return [
            'headers' => ['Date', 'Machine', 'Type', 'Liters', 'Unit Price', 'Total Cost'],
            'rows' => $rows,
            'summary' => [
                'Transactions' => $transactions->count(),
                'Total Liters Dispensed' => round($dispensing->sum('quantity_liters'), 2),
                'Total Cost' => round($transactions->sum('total_cost'), 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $geofenceIds
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    private function materialTracking(int $teamId, Carbon $start, Carbon $end, array $geofenceIds): array
    {
        $query = GeofenceEntry::where('team_id', $teamId)
            ->whereBetween('entry_time', [$start, $end])
            ->with(['machine', 'geofence']);

        if (! empty($geofenceIds)) {
            $query->whereIn('geofence_id', $geofenceIds);
        }

        $entries = $query->orderBy('entry_time')->get();

        $rows = $entries->map(fn ($e) => [
            $e->entry_time?->format('Y-m-d H:i'),
            $e->geofence?->name ?? '—',
            $e->machine?->name ?? '—',
            $e->exit_time?->format('Y-m-d H:i') ?? 'Still inside',
            $e->tonnage_loaded !== null ? $e->tonnage_loaded : null,
            $e->material_type ?? '—',
        ])->all();

        return [
            'headers' => ['Entry Time', 'Geofence', 'Machine', 'Exit Time', 'Tonnage', 'Material'],
            'rows' => $rows,
            'summary' => [
                'Entries' => $entries->count(),
                'Total Tonnage' => round($entries->sum('tonnage_loaded'), 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $machineIds
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    private function downtimeAnalysis(int $teamId, Carbon $start, Carbon $end, array $machineIds): array
    {
        $query = MaintenanceRecord::where('team_id', $teamId)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->with('machine');

        if (! empty($machineIds)) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->orderBy('completed_at')->get();

        $rows = $records->map(function ($r) {
            $downtimeHours = round($r->started_at->diffInMinutes($r->completed_at) / 60, 2);

            return [
                $r->machine?->name ?? '—',
                $r->title,
                ucfirst($r->maintenance_type ?? '—'),
                $r->started_at->format('Y-m-d H:i'),
                $r->completed_at->format('Y-m-d H:i'),
                $downtimeHours,
            ];
        })->all();

        $totalDowntimeHours = $records->sum(fn ($r) => $r->started_at->diffInMinutes($r->completed_at) / 60);

        return [
            'headers' => ['Machine', 'Reason', 'Type', 'Started', 'Completed', 'Downtime (hrs)'],
            'rows' => $rows,
            'summary' => [
                'Downtime Events' => $records->count(),
                'Total Downtime Hours' => round($totalDowntimeHours, 2),
            ],
        ];
    }

    /**
     * Mine Health and Safety Act (MHSA) / DMRE-style compliance export --
     * violation register with detection date, severity, remediation
     * deadline, and resolution status, plus a compliance score summary
     * a Mine Manager can attach to a regulator submission.
     *
     * @return array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}
     */
    private function compliance(int $teamId, Carbon $start, Carbon $end): array
    {
        $violations = ComplianceViolation::where('team_id', $teamId)
            ->whereBetween('detected_at', [$start, $end])
            ->orderBy('detected_at')
            ->get();

        $rows = $violations->map(fn (ComplianceViolation $v) => [
            $v->detected_at?->format('Y-m-d H:i'),
            ucfirst(str_replace('_', ' ', $v->violation_type)),
            ucfirst($v->severity),
            $v->description,
            $v->remediation_deadline?->format('Y-m-d') ?? '—',
            $v->resolved_at !== null
                ? 'Resolved '.$v->resolved_at->format('Y-m-d')
                : ($v->remediation_deadline && $v->remediation_deadline->isPast() ? 'Overdue' : 'Open'),
        ])->all();

        $resolvedCount = $violations->whereNotNull('resolved_at')->count();
        $overdueCount = $violations->filter(fn (ComplianceViolation $v) => $v->resolved_at === null
            && $v->remediation_deadline !== null
            && $v->remediation_deadline->isPast()
        )->count();
        $criticalCount = $violations->where('severity', 'critical')->count();

        // 100 minus a weighted deduction for unresolved and overdue violations,
        // floored at 0 -- mirrors the deduction-based scoring already used
        // elsewhere in the codebase for compliance scoring.
        $score = 100 - ($violations->count() - $resolvedCount) * 5 - $overdueCount * 10 - $criticalCount * 5;

        return [
            'headers' => ['Detected', 'Violation Type', 'Severity', 'Description', 'Remediation Deadline', 'Status'],
            'rows' => $rows,
            'summary' => [
                'Total Violations' => $violations->count(),
                'Resolved' => $resolvedCount,
                'Overdue' => $overdueCount,
                'Critical' => $criticalCount,
                'Compliance Score' => max(0, min(100, $score)),
            ],
        ];
    }
}
