<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Alert API Controller
 *
 * Handles alert management and actions
 */
class AlertController extends Controller
{
    /**
     * List all alerts for current team
     *
     * GET /api/alerts
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:active,acknowledged,resolved',
            'priority' => 'nullable|string|in:critical,high,medium,low',
            'type' => 'nullable|string',
            'machine_id' => 'nullable|integer',
        ]);

        $query = Alert::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }

        $alerts = $query->with('machine')
            ->orderBy('triggered_at', 'desc')
            ->paginate(PageSize::from($request));

        return ApiResponse::paginated($alerts, AlertResource::class);
    }

    /**
     * Get a single alert
     *
     * GET /api/alerts/{id}
     */
    public function show(Alert $alert): JsonResponse
    {
        return response()->json([
            'data' => AlertResource::make($alert->load('machine', 'acknowledgedBy', 'resolvedBy')),
        ]);
    }

    /**
     * Create a new alert (usually triggered by system)
     *
     * POST /api/alerts
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage', Alert::class);

        $validated = $request->validate([
            'machine_id' => 'nullable|integer|exists:machines,id',
            'type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:critical,high,medium,low',
            'metadata' => 'nullable|json',
        ]);

        $validated['team_id'] = auth()->user()?->current_team_id;
        $validated['status'] = 'active';

        $alert = Alert::create($validated);

        return response()->json([
            'data' => AlertResource::make($alert),
            'message' => 'Alert created successfully',
        ], Response::HTTP_CREATED);
    }

    /**
     * Acknowledge an alert
     *
     * POST /api/alerts/{id}/acknowledge
     */
    public function acknowledge(Alert $alert): JsonResponse
    {
        $this->authorize('acknowledge', $alert);

        $alert->acknowledge(CurrentUser::get()?->id);

        return response()->json([
            'data' => AlertResource::make($alert),
            'message' => 'Alert acknowledged successfully',
        ]);
    }

    /**
     * Resolve an alert
     *
     * POST /api/alerts/{id}/resolve
     */
    public function resolve(Alert $alert): JsonResponse
    {
        $this->authorize('resolve', $alert);

        $alert->resolve(CurrentUser::get()?->id);

        return response()->json([
            'data' => AlertResource::make($alert),
            'message' => 'Alert resolved successfully',
        ]);
    }

    /**
     * Get active alerts count
     *
     * GET /api/alerts/stats/active
     */
    public function activeCount(): JsonResponse
    {
        $counts = [
            'critical' => Alert::where('status', 'active')
                ->where('priority', 'critical')
                ->count(),
            'high' => Alert::where('status', 'active')
                ->where('priority', 'high')
                ->count(),
            'medium' => Alert::where('status', 'active')
                ->where('priority', 'medium')
                ->count(),
            'low' => Alert::where('status', 'active')
                ->where('priority', 'low')
                ->count(),
        ];

        $counts['total'] = array_sum($counts);

        return response()->json([
            'data' => $counts,
        ]);
    }

    /**
     * Get alerts for machine
     *
     * GET /api/alerts/machine/{machineId}
     *
     * @param  int|string  $machineId
     */
    public function machineAlerts(Request $request, $machineId): JsonResponse
    {
        $query = Alert::where('machine_id', $machineId);

        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        $alerts = $query->orderBy('triggered_at', 'desc')->limit(50)->get();

        return ApiResponse::collection($alerts->all(), AlertResource::class);
    }
}
