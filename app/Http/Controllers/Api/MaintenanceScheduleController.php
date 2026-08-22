<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Services\MaintenanceHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceScheduleController extends Controller
{
    public function __construct(
        protected MaintenanceHealthService $maintenanceService
    ) {}

    /**
     * Get all maintenance schedules
     */
    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceSchedule::with(['machine', 'team'])
            ->where('team_id', $this->currentTeamId($request));

        // Filter by machine
        if ($request->filled('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by schedule type
        if ($request->filled('schedule_type')) {
            $query->where('schedule_type', $request->input('schedule_type'));
        }

        // Filter by maintenance type
        if ($request->filled('maintenance_type')) {
            $query->where('maintenance_type', $request->input('maintenance_type'));
        }

        // Filter due/overdue
        if ($request->boolean('due_only')) {
            $query->due();
        }
        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        $schedules = $query->latest('next_service_date')->paginate(50);

        return response()->json($schedules);
    }

    /**
     * Create maintenance schedule
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'maintenance_type' => 'required|in:preventive,predictive,corrective,routine,emergency',
            'schedule_type' => 'required|in:hours,kilometers,calendar,condition',
            'interval_hours' => 'nullable|integer|min:1',
            'interval_km' => 'nullable|integer|min:1',
            'interval_days' => 'nullable|integer|min:1',
            'next_service_date' => 'nullable|date',
            'next_service_hours' => 'nullable|integer',
            'next_service_km' => 'nullable|integer',
            'priority' => 'required|in:low,medium,high,critical',
            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'required_parts' => 'nullable|array',
            'required_tools' => 'nullable|array',
            'instructions' => 'nullable|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        $this->authorize('update', $machine);

        $validated['team_id'] = $this->currentTeamId($request);
        $validated['status'] = 'active';

        $schedule = MaintenanceSchedule::create($validated);

        return response()->json([
            'message' => 'Maintenance schedule created successfully',
            'data' => $schedule->load('machine'),
        ], 201);
    }

    /**
     * Get specific schedule
     */
    public function show(MaintenanceSchedule $schedule): JsonResponse
    {
        $this->authorize('view', $schedule);

        return response()->json($schedule->load(['machine', 'maintenanceRecords']));
    }

    /**
     * Update maintenance schedule
     */
    public function update(Request $request, MaintenanceSchedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'maintenance_type' => 'sometimes|in:preventive,predictive,corrective,routine,emergency',
            'schedule_type' => 'sometimes|in:hours,kilometers,calendar,condition',
            'interval_hours' => 'nullable|integer|min:1',
            'interval_km' => 'nullable|integer|min:1',
            'interval_days' => 'nullable|integer|min:1',
            'next_service_date' => 'nullable|date',
            'next_service_hours' => 'nullable|integer',
            'next_service_km' => 'nullable|integer',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:active,due,overdue,completed,cancelled',
            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'required_parts' => 'nullable|array',
            'required_tools' => 'nullable|array',
            'instructions' => 'nullable|string',
        ]);

        $schedule->update($validated);

        return response()->json([
            'message' => 'Maintenance schedule updated successfully',
            'data' => $schedule->load('machine'),
        ]);
    }

    /**
     * Delete maintenance schedule
     */
    public function destroy(MaintenanceSchedule $schedule): JsonResponse
    {
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return response()->json([
            'message' => 'Maintenance schedule deleted successfully',
        ]);
    }

    /**
     * Check schedules for a machine
     */
    public function checkSchedules(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        $updates = $this->maintenanceService->checkSchedules($machine);

        return response()->json([
            'message' => 'Schedules checked successfully',
            'updated_schedules' => count($updates),
            'data' => $updates,
        ]);
    }

    /**
     * Get due and overdue schedules
     */
    public function dueSchedules(Request $request): JsonResponse
    {
        $teamId = $this->currentTeamId($request);

        $due = MaintenanceSchedule::where('team_id', $teamId)
            ->with('machine')
            ->due()
            ->get();

        $overdue = MaintenanceSchedule::where('team_id', $teamId)
            ->with('machine')
            ->overdue()
            ->get();

        return response()->json([
            'due' => $due,
            'overdue' => $overdue,
            'summary' => [
                'due_count' => $due->count(),
                'overdue_count' => $overdue->count(),
            ],
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
