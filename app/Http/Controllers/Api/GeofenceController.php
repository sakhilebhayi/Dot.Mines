<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\GeofenceResource;
use App\Http\Resources\MachineResource;
use App\Models\Geofence;
use App\Support\ApiPayload;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Geofence API Controller
 *
 * Handles pit/stockpile area management
 * CRUD operations and statistics
 */
class GeofenceController extends Controller
{
    /**
     * List all geofences for current team
     *
     * GET /api/geofences
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|string|in:pit,stockpile,dump,facility',
            'search' => 'nullable|string|max:100',
        ]);

        $query = Geofence::query();

        if ($request->filled('search')) {
            $search = ApiPayload::str($request->input('search'));
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        /** @psalm-suppress MixedAssignment */
        $perPageRaw = $request->input('per_page');
        $perPage = is_numeric($perPageRaw) ? (int) $perPageRaw : 15;
        $geofences = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::paginated($geofences, GeofenceResource::class);
    }

    /**
     * Get a single geofence
     *
     * GET /api/geofences/{id}
     */
    public function show(Geofence $geofence): JsonResponse
    {
        $activeMachines = $geofence->activeMachines()->map(function ($machine) {
            return [
                'id' => $machine->id,
                'name' => $machine->name,
                'registration_number' => $machine->registration_number,
            ];
        });

        return response()->json([
            'data' => array_merge($geofence->toArray(), [
                'active_machines_count' => $activeMachines->count(),
                'active_machines' => $activeMachines,
            ]),
        ]);
    }

    /**
     * Create a new geofence
     *
     * POST /api/geofences
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Geofence::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:pit,stockpile,dump,facility',
            'description' => 'nullable|string',
            'coordinates' => 'required|json',
            'center_latitude' => 'required|numeric|between:-90,90',
            'center_longitude' => 'required|numeric|between:-180,180',
            'area_sqm' => 'nullable|numeric|min:0',
            'perimeter_m' => 'nullable|numeric|min:0',
        ]);

        $validated['team_id'] = auth()->user()?->current_team_id;
        $validated['status'] = 'active';
        /** @psalm-suppress MixedAssignment */
        $validated['coordinates'] = json_decode(ApiPayload::str($request->input('coordinates'), '[]'), true);

        $geofence = Geofence::create($validated);

        return response()->json([
            'data' => GeofenceResource::make($geofence),
            'message' => 'Geofence created successfully',
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a geofence
     *
     * PUT /api/geofences/{id}
     */
    public function update(Request $request, Geofence $geofence): JsonResponse
    {
        $this->authorize('update', $geofence);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'coordinates' => 'sometimes|required|json',
            'center_latitude' => 'sometimes|required|numeric|between:-90,90',
            'center_longitude' => 'sometimes|required|numeric|between:-180,180',
            'area_sqm' => 'nullable|numeric|min:0',
            'perimeter_m' => 'nullable|numeric|min:0',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        if (isset($validated['coordinates'])) {
            /** @psalm-suppress MixedAssignment */
            $validated['coordinates'] = json_decode(ApiPayload::str($validated['coordinates'], '[]'), true);
        }

        $geofence->update($validated);

        return response()->json([
            'data' => GeofenceResource::make($geofence),
            'message' => 'Geofence updated successfully',
        ]);
    }

    /**
     * Delete a geofence
     *
     * DELETE /api/geofences/{id}
     */
    public function destroy(Geofence $geofence): JsonResponse
    {
        $this->authorize('delete', $geofence);

        $geofence->delete();

        return response()->json([
            'message' => 'Geofence deleted successfully',
        ]);
    }

    /**
     * Get entry/exit records for a geofence
     *
     * GET /api/geofences/{id}/entries
     */
    public function entries(Request $request, Geofence $geofence): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = $geofence->entries()->with('machine');

        if ($request->filled('start_date')) {
            $query->where('entry_time', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('entry_time', '<=', $request->input('end_date'));
        }

        /** @psalm-suppress MixedAssignment */
        $limitRaw = $request->input('limit');
        $limit = is_numeric($limitRaw) ? (int) $limitRaw : 100;
        $entries = $query->orderBy('entry_time', 'desc')->limit($limit)->get();

        return response()->json([
            'data' => $entries,
        ]);
    }

    /**
     * Get tonnage statistics for date range
     *
     * GET /api/geofences/{id}/tonnage-stats
     */
    public function tonnageStats(Request $request, Geofence $geofence): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $dateFrom = ApiPayload::str($request->input('start_date'));
        $dateTo = ApiPayload::str($request->input('end_date'));

        $totalTonnage = $geofence->getTonnageForDateRange($dateFrom, $dateTo);
        $entries = $geofence->entries()
            ->whereBetween('entry_time', [$dateFrom, $dateTo])
            ->get();

        $tonnageByMachine = $entries->groupBy('machine_id')->map(function ($group) {
            $first = $group->first();

            return [
                'machine_id' => $first?->machine_id,
                'machine_name' => $first?->machine?->name,
                'tonnage' => $group->sum('tonnage_loaded'),
                'loads' => $group->count(),
            ];
        });

        return response()->json([
            'data' => [
                'total_tonnage' => $totalTonnage,
                'entries_count' => $entries->count(),
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
                'by_machine' => array_values($tonnageByMachine->toArray()),
            ],
        ]);
    }

    /**
     * Get machines currently in geofence
     *
     * GET /api/geofences/{id}/active-machines
     */
    public function activeMachines(Geofence $geofence): JsonResponse
    {
        $machines = $geofence->activeMachines();

        return response()->json([
            'data' => MachineResource::collection($machines),
            'count' => $machines->count(),
        ]);
    }
}
