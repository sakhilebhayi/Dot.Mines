<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelTank;
use App\Models\User;
use App\Services\FuelManagementService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FuelTankController extends Controller
{
    public function __construct(
        protected FuelManagementService $fuelService
    ) {}

    /**
     * Get all fuel tanks for team
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = $this->currentTeamId($request);

        $query = FuelTank::where('team_id', $teamId)
            ->with(['mineArea:id,name']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('fuel_type')) {
            $query->where('fuel_type', $request->input('fuel_type'));
        }

        if ($request->has('low_fuel') && $request->input('low_fuel')) {
            $query->lowFuel();
        }

        if ($request->has('critical') && $request->input('critical')) {
            $query->critical();
        }

        if ($request->has('mine_area_id')) {
            $query->where('mine_area_id', $request->input('mine_area_id'));
        }

        $tanks = $query->latest()->paginate(20);

        // Add calculated fields
        $tanks->getCollection()->transform(function ($tank) {
            $tank->fill_percentage = $tank->fill_percentage;
            $tank->available_capacity = $tank->available_capacity;
            $tank->setAttribute('is_critical', $tank->isCritical());
            $tank->setAttribute('is_below_minimum', $tank->isBelowMinimum());

            return $tank;
        });

        return response()->json($tanks);
    }

    /**
     * Create new fuel tank
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'tank_number' => 'nullable|string|max:100',
            'mine_area_id' => 'nullable|exists:mine_areas,id',
            'location_description' => 'nullable|string',
            'location_latitude' => 'nullable|numeric|between:-90,90',
            'location_longitude' => 'nullable|numeric|between:-180,180',
            'capacity_liters' => 'required|numeric|min:0',
            'current_level_liters' => 'nullable|numeric|min:0',
            'minimum_level_liters' => 'required|numeric|min:0',
            'fuel_type' => 'required|string|in:diesel,petrol,biodiesel,lpg,cng,electric',
            'status' => 'nullable|in:active,maintenance,inactive,decommissioned',
            'last_inspection_date' => 'nullable|date',
            'next_inspection_date' => 'nullable|date|after:last_inspection_date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['team_id'] = $this->currentTeamId($request);
        /** @psalm-suppress MixedAssignment */
        $data['current_level_liters'] = $data['current_level_liters'] ?? 0;

        $tank = FuelTank::create($data);

        return response()->json($tank->load('mineArea'), 201);
    }

    /**
     * Get single fuel tank
     */
    public function show(Request $request, FuelTank $fuelTank): JsonResponse
    {
        // Authorization check
        if ($fuelTank->team_id !== $this->currentTeamId($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $fuelTank->load(['mineArea', 'transactions' => function (Relation $query) {
            $query->latest()->limit(10);
        }, 'alerts' => function (Relation $query) {
            $query->where('status', 'active')->latest()->limit(5);
        }]);

        // Add calculated fields
        $fuelTank->fill_percentage = $fuelTank->fill_percentage;
        $fuelTank->available_capacity = $fuelTank->available_capacity;

        return response()->json($fuelTank);
    }

    /**
     * Update fuel tank
     */
    public function update(Request $request, FuelTank $fuelTank): JsonResponse
    {
        // Authorization check
        if ($fuelTank->team_id !== $this->currentTeamId($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'tank_number' => 'nullable|string|max:100',
            'mine_area_id' => 'nullable|exists:mine_areas,id',
            'location_description' => 'nullable|string',
            'location_latitude' => 'nullable|numeric|between:-90,90',
            'location_longitude' => 'nullable|numeric|between:-180,180',
            'capacity_liters' => 'sometimes|required|numeric|min:0',
            'minimum_level_liters' => 'sometimes|required|numeric|min:0',
            'fuel_type' => 'sometimes|required|string|in:diesel,petrol,biodiesel,lpg,cng,electric',
            'status' => 'nullable|in:active,maintenance,inactive,decommissioned',
            'last_inspection_date' => 'nullable|date',
            'next_inspection_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fuelTank->update($validator->validated());

        return response()->json($fuelTank->load('mineArea'));
    }

    /**
     * Delete fuel tank
     */
    public function destroy(Request $request, FuelTank $fuelTank): JsonResponse
    {
        // Authorization check
        if ($fuelTank->team_id !== $this->currentTeamId($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $fuelTank->delete();

        return response()->json(['message' => 'Fuel tank deleted successfully']);
    }

    /**
     * Get tank statistics
     */
    public function statistics(Request $request, FuelTank $fuelTank): JsonResponse
    {
        // Authorization check
        if ($fuelTank->team_id !== $this->currentTeamId($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        /** @psalm-suppress MixedAssignment */
        $startDate = $request->input('start_date', now()->subDays(30));
        /** @psalm-suppress MixedAssignment */
        $endDate = $request->input('end_date', now());

        $transactions = $fuelTank->transactions()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        $stats = [
            'tank_info' => [
                'id' => $fuelTank->id,
                'name' => $fuelTank->name,
                'capacity' => $fuelTank->capacity_liters,
                'current_level' => $fuelTank->current_level_liters,
                'fill_percentage' => $fuelTank->fill_percentage,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'transactions' => [
                'total_count' => $transactions->count(),
                'total_refills' => $transactions->where('transaction_type', 'refill')->sum('quantity_liters'),
                'total_dispensed' => $transactions->where('transaction_type', 'dispensing')->sum('quantity_liters'),
                'total_deliveries' => $transactions->where('transaction_type', 'delivery')->sum('quantity_liters'),
                'total_cost' => $transactions->sum('total_cost'),
            ],
            'by_type' => $transactions->groupBy('transaction_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_liters' => $group->sum('quantity_liters'),
                    'total_cost' => $group->sum('total_cost'),
                ];
            }),
        ];

        return response()->json($stats);
    }

    /**
     * Resolve the authenticated user's current team id or abort.
     */
    private function currentTeamId(Request $request): int
    {
        $user = $request->user();
        $teamId = $user instanceof User ? $user->currentTeam?->id : null;

        if ($teamId === null) {
            abort(401);
        }

        return $teamId;
    }
}
