<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceRecordResource;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\MaintenanceHealthService;
use App\Support\ApiResponse;
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
        $query = MaintenanceRecord::with(['machine', 'team', 'assignedTo', 'completedBy'])
            ->where('team_id', $this->currentTeamId($request));

        // Filter by machine
        if ($request->filled('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by maintenance type
        if ($request->filled('maintenance_type')) {
            $query->where('maintenance_type', $request->input('maintenance_type'));
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('scheduled_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('scheduled_at', '<=', $request->input('end_date'));
        }

        // Filter by assigned user
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $records = $query->latest('scheduled_at')->paginate(50);

        return ApiResponse::paginated($records, MaintenanceRecordResource::class);
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

        $validated['team_id'] = $this->currentTeamId($request);
        $validated['status'] = 'scheduled';

        $record = $this->maintenanceService->createMaintenanceRecord($validated);

        return response()->json([
            'message' => 'Work order created successfully',
            'data' => MaintenanceRecordResource::make($record->load(['machine', 'assignedTo'])),
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
            'data' => MaintenanceRecordResource::make($record->load(['machine', 'assignedTo'])),
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

        return response()->json([
            'message' => 'Work order completed successfully',
            'data' => MaintenanceRecordResource::make($completedRecord->load(['machine', 'completedBy'])),
        ]);
    }

    /**
     * Delete maintenance record
     */
    public function destroy(MaintenanceRecord $record): JsonResponse
    {
        $this->authorize('delete', $record);

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
        $teamId = $this->currentTeamId($request);

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

        $records = MaintenanceRecord::where('team_id', $this->currentTeamId($request))
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
                    $record->machine?->name,
                    $record->maintenance_type,
                    $record->status,
                    $record->scheduled_date->format('Y-m-d'),
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

    /**
     * Resolve the authenticated user's current team id or abort.
     */
    private function currentTeamId(Request $request): int
    {
        $user = $request->user();
        $teamId = $user instanceof User ? $user->current_team_id : null;

        if ($teamId === null) {
            abort(401);
        }

        return $teamId;
    }
}
