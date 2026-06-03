<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Services\AuditService;
use App\Services\MaintenanceHealthService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceRecordController extends Controller
{
    public function __construct(
        protected MaintenanceHealthService $maintenanceService
    ) {}

    /**
     * Get all maintenance records
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machine_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:scheduled,in_progress,completed,cancelled',
            'maintenance_type' => 'nullable|string|in:preventive,predictive,corrective,routine,emergency',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'assigned_to' => 'nullable|integer|min:1',
        ]);

        $query = MaintenanceRecord::with(['machine', 'team', 'assignedTo', 'completedBy'])
            ->where('team_id', $request->user()->current_team_id);

        if (! empty($validated['machine_id'])) {
            $query->where('machine_id', $validated['machine_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['maintenance_type'])) {
            $query->where('maintenance_type', $validated['maintenance_type']);
        }

        if (! empty($validated['start_date'])) {
            $query->where('scheduled_at', '>=', $validated['start_date']);
        }
        if (! empty($validated['end_date'])) {
            $query->where('scheduled_at', '<=', $validated['end_date']);
        }

        if (! empty($validated['assigned_to'])) {
            $query->where('assigned_to', $validated['assigned_to']);
        }

        $records = $query->latest('scheduled_at')->paginate(50);

        return response()->json($records);
    }

    /**
     * Create maintenance record (work order)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'maintenance_schedule_id' => 'nullable|exists:maintenance_schedules,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'maintenance_type' => 'required|in:preventive,predictive,corrective,routine,emergency',
            'priority' => 'required|in:low,medium,high,critical',
            'scheduled_at' => 'required|date',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        $this->authorize('update', $machine);

        $validated['team_id'] = $request->user()->current_team_id;
        $validated['status'] = 'scheduled';

        $record = $this->maintenanceService->createMaintenanceRecord($validated);

        AuditService::log(
            AuditLog::MAINTENANCE_CREATED,
            "Created maintenance work order: {$record->title} for machine #{$record->machine_id}",
            $record,
            ['machine_id' => $record->machine_id, 'type' => $record->maintenance_type, 'priority' => $record->priority]
        );

        return response()->json([
            'message' => 'Work order created successfully',
            'data' => $record->load(['machine', 'assignedTo']),
        ], 201);
    }

    /**
     * Get specific maintenance record
     */
    public function show(MaintenanceRecord $record): JsonResponse
    {
        $this->authorize('view', $record);

        return response()->json($record->load([
            'machine',
            'maintenanceSchedule',
            'assignedTo',
            'completedBy',
        ]));
    }

    /**
     * Update maintenance record
     */
    public function update(Request $request, MaintenanceRecord $record): JsonResponse
    {
        $this->authorize('update', $record);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'maintenance_type' => 'sometimes|in:preventive,predictive,corrective,routine,emergency',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'scheduled_at' => 'sometimes|date',
            'started_at' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
        ]);

        $record->update($validated);

        return response()->json([
            'message' => 'Work order updated successfully',
            'data' => $record->load(['machine', 'assignedTo']),
        ]);
    }

    /**
     * Complete maintenance record
     */
    public function complete(Request $request, MaintenanceRecord $record): JsonResponse
    {
        $this->authorize('update', $record);

        $validated = $request->validate([
            'completed_by' => 'required|exists:users,id',
            'labor_hours' => 'required|numeric|min:0',
            'labor_cost' => 'required|numeric|min:0',
            'parts_cost' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
            'parts_used' => 'nullable|array',
            'work_performed' => 'required|string',
            'fault_codes_cleared' => 'nullable|array',
            'technician_notes' => 'nullable|string',
            'machine_operational' => 'required|boolean',
            'hour_meter_reading' => 'nullable|integer',
            'odometer_reading' => 'nullable|integer',
        ]);

        $completedRecord = $this->maintenanceService->completeMaintenanceRecord($record, $validated);

        AuditService::log(
            AuditLog::MAINTENANCE_COMPLETED,
            "Completed maintenance work order: {$completedRecord->title} (machine #{$completedRecord->machine_id})",
            $completedRecord,
            [
                'machine_id' => $completedRecord->machine_id,
                'labor_hours' => $validated['labor_hours'],
                'total_cost' => $validated['total_cost'],
                'completed_by' => $validated['completed_by'],
            ]
        );

        return response()->json([
            'message' => 'Work order completed successfully',
            'data' => $completedRecord->load(['machine', 'completedBy']),
        ]);
    }

    /**
     * Delete maintenance record
     */
    public function destroy(MaintenanceRecord $record): JsonResponse
    {
        $this->authorize('delete', $record);

        AuditService::log(
            AuditLog::MAINTENANCE_DELETED,
            "Deleted maintenance work order: {$record->title} (machine #{$record->machine_id})",
            $record,
            ['machine_id' => $record->machine_id, 'title' => $record->title]
        );

        $record->delete();

        return response()->json([
            'message' => 'Work order deleted successfully',
        ]);
    }

    /**
     * Get maintenance analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $teamId = $request->user()->current_team_id;

        $analytics = $this->maintenanceService->getMaintenanceAnalytics($teamId, $startDate, $endDate);

        return response()->json($analytics);
    }

    /**
     * Export maintenance records
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'format' => 'sometimes|in:csv,json',
        ]);

        $records = MaintenanceRecord::where('team_id', $request->user()->current_team_id)
            ->with(['machine', 'assignedTo', 'completedBy'])
            ->whereBetween('completed_at', [
                $validated['start_date'],
                $validated['end_date'],
            ])
            ->completed()
            ->get();

        if ($request->input('format', 'json') === 'csv') {
            $csv = "Work Order,Machine,Type,Status,Scheduled,Completed,Duration,Labor Cost,Parts Cost,Total Cost\n";

            foreach ($records as $record) {
                $csv .= implode(',', [
                    $record->work_order_number,
                    $record->machine->name,
                    $record->maintenance_type,
                    $record->status,
                    $record->scheduled_at->format('Y-m-d'),
                    $record->completed_at?->format('Y-m-d') ?? 'N/A',
                    $record->duration ?? 'N/A',
                    $record->labor_cost,
                    $record->parts_cost,
                    $record->total_cost,
                ])."\n";
            }

            return response()->json([
                'format' => 'csv',
                'data' => $csv,
                'filename' => "maintenance-records-{$validated['start_date']}-{$validated['end_date']}.csv",
            ]);
        }

        return response()->json([
            'format' => 'json',
            'data' => $records,
            'count' => $records->count(),
        ]);
    }
}
