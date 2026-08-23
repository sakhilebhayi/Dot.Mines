<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientAllocationException;
use App\Http\Resources\AlertResource;
use App\Http\Resources\MachineResource;
use App\Models\Machine;
use App\Services\Billing\MachineProvisioningService;
use App\Support\ApiPayload;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
            'status' => 'nullable|string|in:active,idle,maintenance,offline',
            'type' => 'nullable|string',
            'search' => 'nullable|string|max:100',
        ]);

        $query = Machine::query();

        // Search by name, registration number, or serial number
        if ($request->filled('search')) {
            $search = ApiPayload::str($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('machine_type', $request->input('type'));
        }

        // Sorting
        $sort = ApiPayload::str($request->input('sort'), 'created_at');
        $query->orderBy($sort, 'desc');

        // Eager load relationships to prevent N+1 queries
        $query->with('integration');

        // Pagination
        $machines = $query->paginate(PageSize::from($request));

        return ApiResponse::paginated($machines, MachineResource::class);
    }

    /**
     * Get a single machine by ID
     *
     * GET /api/machines/{id}
     */
    public function show(Machine $machine): JsonResponse
    {
        return response()->json([
            'data' => MachineResource::make($machine->load('alerts', 'mineArea')),
        ]);
    }

    /**
     * Create a new machine
     *
     * POST /api/machines
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Machine::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'machine_type' => 'required|string|in:volvo,cat,komatsu,bell,ldv',
            'model' => 'nullable|string|max:100',
            'registration_number' => 'required|string|unique:machines,registration_number',
            'serial_number' => 'required|string|unique:machines,serial_number',
            'manufacturer_id' => 'nullable|string|max:255',
            'capacity' => 'nullable|numeric|min:0',
            'fuel_capacity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['team_id'] = auth()->user()?->current_team_id;
        $validated['status'] = 'active';

        // Same entitlement gate as every other creation path -- the API is
        // not a way around the allocation limit (brief §4).
        try {
            $machine = app(MachineProvisioningService::class)->provision(
                CurrentUser::team(),
                $validated['machine_type'],
                fn (): Machine => Machine::create($validated),
            );
        } catch (InsufficientAllocationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['allocation' => [$e->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => MachineResource::make($machine),
            'message' => 'Machine created successfully',
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a machine
     *
     * PUT /api/machines/{id}
     */
    public function update(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('update', $machine);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'model' => 'nullable|string|max:100',
            'status' => 'sometimes|required|string|in:active,idle,maintenance,offline',
            'capacity' => 'nullable|numeric|min:0',
            'fuel_capacity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $machine->update($validated);

        return response()->json([
            'data' => MachineResource::make($machine),
            'message' => 'Machine updated successfully',
        ]);
    }

    /**
     * Delete a machine
     *
     * DELETE /api/machines/{id}
     */
    public function destroy(Machine $machine): JsonResponse
    {
        $this->authorize('delete', $machine);

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
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:1000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'hours_back' => 'nullable|integer|min:1|max:720', // shorthand, up to 30 days
        ]);

        $query = $machine->metrics();

        // start_date/end_date is the API-wide way to bound a time range;
        // hours_back is kept as a shorthand for "the last N hours".
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', ApiPayload::str($request->input('start_date')));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', ApiPayload::str($request->input('end_date')));
        }

        if ($request->filled('hours_back') && ! $request->filled('start_date')) {
            /** @psalm-suppress MixedAssignment */
            $hoursBackRaw = $request->input('hours_back');
            $query->where('created_at', '>=', now()->subHours(is_numeric($hoursBackRaw) ? (int) $hoursBackRaw : 24));
        }

        /** @psalm-suppress MixedAssignment */
        $limitRaw = $request->input('limit');
        $metrics = $query->latest('created_at')->limit(is_numeric($limitRaw) ? (int) $limitRaw : 100)->get();

        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Update machine location
     *
     * POST /api/machines/{id}/location
     */
    public function updateLocation(Request $request, Machine $machine): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $machine->updateLocation($validated['latitude'], $validated['longitude']);

        return response()->json([
            'data' => MachineResource::make($machine),
            'message' => 'Location updated successfully',
        ]);
    }

    /**
     * Get active alerts for a machine
     *
     * GET /api/machines/{id}/alerts
     */
    public function alerts(Machine $machine): JsonResponse
    {
        $alerts = $machine->activeAlerts()
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'data' => AlertResource::collection($alerts),
        ]);
    }
}
