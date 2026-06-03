<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Services\MaintenanceHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineHealthController extends Controller
{
    public function __construct(
        protected MaintenanceHealthService $maintenanceService
    ) {}

    /**
     * Get health status for all machines
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'health_status' => 'nullable|string|in:excellent,good,fair,poor,critical',
            'min_score' => 'nullable|numeric|min:0|max:100',
            'max_score' => 'nullable|numeric|min:0|max:100',
            'needs_attention' => 'nullable|boolean',
            'critical_only' => 'nullable|boolean',
        ]);

        $query = MachineHealthStatus::with(['machine', 'team'])
            ->where('team_id', $request->user()->current_team_id);

        if (! empty($validated['health_status'])) {
            $query->where('health_status', $validated['health_status']);
        }

        if (isset($validated['min_score'])) {
            $query->where('overall_health_score', '>=', $validated['min_score']);
        }
        if (isset($validated['max_score'])) {
            $query->where('overall_health_score', '<=', $validated['max_score']);
        }

        if ($request->boolean('needs_attention')) {
            $query->needsAttention();
        }

        if ($request->boolean('critical_only')) {
            $query->critical();
        }

        $healthStatuses = $query->latest('updated_at')->paginate(50);

        return response()->json($healthStatuses);
    }

    /**
     * Get health status for specific machine
     */
    public function show(Machine $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        $report = $this->maintenanceService->getMachineHealthReport($machine);

        return response()->json($report);
    }

    /**
     * Update health status
     */
    public function update(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('update', $machine);

        $validated = $request->validate([
            'engine_health' => 'nullable|numeric|min:0|max:100',
            'transmission_health' => 'nullable|numeric|min:0|max:100',
            'hydraulics_health' => 'nullable|numeric|min:0|max:100',
            'electrical_health' => 'nullable|numeric|min:0|max:100',
            'brakes_health' => 'nullable|numeric|min:0|max:100',
            'cooling_system_health' => 'nullable|numeric|min:0|max:100',
            'active_fault_codes' => 'nullable|array',
            'fault_code_count' => 'nullable|integer|min:0',
            'last_diagnostic_scan' => 'nullable|date',
            'recommendations' => 'nullable|string',
        ]);

        $healthStatus = $this->maintenanceService->updateHealthStatus($machine, $validated);

        return response()->json([
            'message' => 'Health status updated successfully',
            'data' => $healthStatus->load('machine'),
        ]);
    }

    /**
     * Run diagnostic scan
     */
    public function diagnostic(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('update', $machine);

        $validated = $request->validate([
            'diagnostic_data' => 'required|array',
            'diagnostic_data.*.component' => 'required|string',
            'diagnostic_data.*.score' => 'required|numeric|min:0|max:100',
            'fault_codes' => 'nullable|array',
        ]);

        $healthData = [
            'last_diagnostic_scan' => now(),
            'active_fault_codes' => $validated['fault_codes'] ?? [],
            'fault_code_count' => count($validated['fault_codes'] ?? []),
        ];

        // Map component scores
        foreach ($validated['diagnostic_data'] as $component) {
            $componentName = $component['component'];
            $score = $component['score'];

            $healthData["{$componentName}_health"] = $score;
        }

        $healthStatus = $this->maintenanceService->updateHealthStatus($machine, $healthData);

        return response()->json([
            'message' => 'Diagnostic scan completed',
            'data' => $healthStatus,
        ]);
    }

    /**
     * Get health statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $teamId = $request->user()->current_team_id;

        $stats = [
            'total_machines' => Machine::where('team_id', $teamId)->count(),
            'health_distribution' => MachineHealthStatus::where('team_id', $teamId)
                ->selectRaw('health_status, COUNT(*) as count')
                ->groupBy('health_status')
                ->get()
                ->pluck('count', 'health_status'),
            'average_health_score' => MachineHealthStatus::where('team_id', $teamId)
                ->avg('overall_health_score'),
            'machines_needing_attention' => MachineHealthStatus::where('team_id', $teamId)
                ->needsAttention()
                ->count(),
            'critical_machines' => MachineHealthStatus::where('team_id', $teamId)
                ->critical()
                ->count(),
            'total_active_fault_codes' => MachineHealthStatus::where('team_id', $teamId)
                ->sum('fault_code_count'),
        ];

        return response()->json($stats);
    }
}
