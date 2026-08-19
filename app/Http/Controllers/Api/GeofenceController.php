<?php

namespace App\Http\Controllers\Api;

use App\Models\Geofence;
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
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|string|in:pit,stockpile,dump,facility',
            'search' => 'nullable|string|max:100',
        ]);

        $query = Geofence::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = $request->input('per_page', 15);
        $geofences = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $geofences->items(),
            'pagination' => [
                'total' => $geofences->total(),
                'per_page' => $geofences->perPage(),
                'current_page' => $geofences->currentPage(),
                'last_page' => $geofences->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single geofence
     *
     * GET /api/geofences/{id}
     */
    public function show(Geofence $geofence)
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
    public function store(Request $request)
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

        $validated['team_id'] = auth()->user()->current_team_id;
        $validated['status'] = 'active';
        $validated['coordinates'] = json_decode($request->input('coordinates'), true);

        $geofence = Geofence::create($validated);

        return response()->json([
            'data' => $geofence,
            'message' => 'Geofence created successfully',
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a geofence
     *
     * PUT /api/geofences/{id}
     */
    public function update(Request $request, Geofence $geofence)
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
            $validated['coordinates'] = json_decode($validated['coordinates'], true);
        }

        $geofence->update($validated);

        return response()->json([
            'data' => $geofence,
            'message' => 'Geofence updated successfully',
        ]);
    }

    /**
     * Delete a geofence
     *
     * DELETE /api/geofences/{id}
     */
    public function destroy(Geofence $geofence)
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
    public function entries(Request $request, Geofence $geofence)
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = $geofence->entries()->with('machine');

        if ($request->filled('date_from')) {
            $query->where('entry_time', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('entry_time', '<=', $request->input('date_to'));
        }

        $limit = $request->input('limit', 100);
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
    public function tonnageStats(Request $request, Geofence $geofence)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $totalTonnage = $geofence->getTonnageForDateRange($dateFrom, $dateTo);
        $entries = $geofence->entries()
            ->whereBetween('entry_time', [$dateFrom, $dateTo])
            ->get();

        $tonnageByMachine = $entries->groupBy('machine_id')->map(function ($entries) {
            return [
                'machine_id' => $entries->first()->machine_id,
                'machine_name' => $entries->first()->machine->name,
                'tonnage' => $entries->sum('tonnage_loaded'),
                'loads' => $entries->count(),
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
    public function activeMachines(Geofence $geofence)
    {
        $machines = $geofence->activeMachines();

        return response()->json([
            'data' => $machines,
            'count' => $machines->count(),
        ]);
    }
}
