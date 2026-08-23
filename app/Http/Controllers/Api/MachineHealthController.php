<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MachineHealthResource;
use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Models\User;
use App\Services\MaintenanceHealthService;
use App\Support\ApiResponse;
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
        $query = MachineHealthStatus::with(['machine', 'team'])
            ->where('team_id', $this->currentTeamId($request));

        // Filter by health status
        if ($request->filled('health_status')) {
            $query->where('health_status', $request->input('health_status'));
        }

        // Filter by score range
        if ($request->filled('min_score')) {
            $query->where('overall_health_score', '>=', $request->input('min_score'));
        }
        if ($request->filled('max_score')) {
            $query->where('overall_health_score', '<=', $request->input('max_score'));
        }

        // Filter machines needing attention
        if ($request->boolean('needs_attention')) {
            $query->needsAttention();
        }

        // Filter critical
        if ($request->boolean('critical_only')) {
            $query->critical();
        }

        $healthStatuses = $query->latest('updated_at')->paginate(50);

        return ApiResponse::paginated($healthStatuses, MachineHealthResource::class);
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
            'data' => MachineHealthResource::make($healthStatus->load('machine')),
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
            'data' => MachineHealthResource::make($healthStatus),
        ]);
    }

    /**
     * Get health statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $teamId = $this->currentTeamId($request);

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
