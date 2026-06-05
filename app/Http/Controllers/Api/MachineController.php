<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreMachineRequest;
use App\Http\Requests\UpdateMachineRequest;
use App\Http\Resources\AlertResource;
use App\Http\Resources\MachineResource;
use App\Models\AuditLog;
use App\Models\Machine;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine API Controller
 *
 * Handles all machine-related API endpoints
 * GET, POST, PUT, DELETE operations
 */
class MachineController extends Controller
{
    /**
     * List all machines for current team
     *
     * GET /api/machines
     * Query params: page, per_page, sort, filter
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:name,machine_type,status,created_at',
            'filter_status' => 'nullable|string|in:active,idle,maintenance,offline',
            'filter_type' => 'nullable|string',
            'search' => 'nullable|string|max:100',
        ]);

        $query = Machine::query();

        // Search by name, registration number, or serial number
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('filter_status')) {
            $query->where('status', $request->input('filter_status'));
        }

        // Filter by type
        if ($request->filled('filter_type')) {
            $query->where('machine_type', $request->input('filter_type'));
        }

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $query->orderBy($sort, 'desc');

        // Eager load relationships to prevent N+1 queries
        $query->with('integration');

        // Pagination
        $perPage = $request->input('per_page', 15);
        $machines = $query->paginate($perPage);

        return response()->json([
            'data' => $machines->items(),
            'pagination' => [
                'total' => $machines->total(),
                'per_page' => $machines->perPage(),
                'current_page' => $machines->currentPage(),
                'last_page' => $machines->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single machine by ID
     *
     * GET /api/machines/{id}
     */
    public function show(Machine $machine): MachineResource
    {
        $this->authorize('view', $machine);

        $machine->load(['metrics' => fn ($q) => $q->latest('created_at')->limit(20), 'alerts', 'integration']);

        return MachineResource::make($machine);
    }

    /**
     * Create a new machine
     *
     * POST /api/machines
     */
    public function store(StoreMachineRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['team_id'] = Auth::user()->current_team_id;
        $validated['status'] = 'active';

        $machine = Machine::create($validated);

        AuditService::log(
            AuditLog::MACHINE_CREATED,
            "Created machine: {$machine->name} ({$machine->machine_type})",
            $machine,
            ['registration_number' => $machine->registration_number, 'machine_type' => $machine->machine_type]
        );

        return MachineResource::make($machine)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a machine
     *
     * PUT /api/machines/{id}
     */
    public function update(UpdateMachineRequest $request, Machine $machine): MachineResource
    {
        $validated = $request->validated();

        $previousValues = array_intersect_key($machine->toArray(), $validated);
        $machine->update($validated);

        AuditService::log(
            AuditLog::MACHINE_UPDATED,
            "Updated machine: {$machine->name}",
            $machine,
            ['previous' => $previousValues, 'updated' => $validated]
        );

        return MachineResource::make($machine->fresh());
    }

    /**
     * Delete a machine
     *
     * DELETE /api/machines/{id}
     */
    public function destroy(Machine $machine): JsonResponse
    {
        $this->authorize('delete', $machine);

        AuditService::log(
            AuditLog::MACHINE_DELETED,
            "Deleted machine: {$machine->name} (#{$machine->id})",
            $machine,
            ['name' => $machine->name, 'registration_number' => $machine->registration_number]
        );

        $machine->delete();

        return response()->json([
            'message' => 'Machine deleted successfully',
        ]);
    }

    /**
     * Get latest metrics for a machine
     *
     * GET /api/machines/{id}/metrics
     */
    public function metrics(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:1000',
            'hours_back' => 'nullable|integer|min:1|max:720', // up to 30 days
        ]);

        $query = $machine->metrics();

        // Filter by hours back if specified
        if ($request->filled('hours_back')) {
            $hoursBack = $request->input('hours_back');
            $query->where('created_at', '>=', now()->subHours($hoursBack));
        }

        $limit = $request->input('limit', 100);
        $metrics = $query->latest('created_at')->limit($limit)->get();

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Update machine location
     *
     * POST /api/machines/{id}/location
     */
    public function updateLocation(Request $request, Machine $machine): MachineResource
    {
        $this->authorize('update', $machine);

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $machine->updateLocation($validated['latitude'], $validated['longitude']);

        return MachineResource::make($machine->fresh());
    }

    /**
     * Get active alerts for a machine
     *
     * GET /api/machines/{id}/alerts
     */
    public function alerts(Machine $machine): AnonymousResourceCollection
    {
        $this->authorize('view', $machine);

        $alerts = $machine->activeAlerts()
            ->orderBy('priority', 'desc')
            ->get();

        return AlertResource::collection($alerts);
    }
}
