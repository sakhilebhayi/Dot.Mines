<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelTransaction;
use App\Services\FuelManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuelTransactionController extends Controller
{
    public function __construct(
        protected FuelManagementService $fuelService
    ) {}

    /**
     * Get all fuel transactions for team
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam->id;

        $validated = $request->validate([
            'transaction_type' => 'nullable|string|in:refill,dispensing,delivery,transfer,adjustment,theft,spillage',
            'fuel_type' => 'nullable|string|in:diesel,petrol,biodiesel,lpg,cng,electric',
            'fuel_tank_id' => 'nullable|integer|min:1',
            'machine_id' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'supplier' => 'nullable|string|max:255',
        ]);

        $query = FuelTransaction::where('team_id', $teamId)
            ->with(['fuelTank:id,name', 'machine:id,name', 'user:id,name']);

        if (! empty($validated['transaction_type'])) {
            $query->where('transaction_type', $validated['transaction_type']);
        }

        if (! empty($validated['fuel_type'])) {
            $query->where('fuel_type', $validated['fuel_type']);
        }

        if (! empty($validated['fuel_tank_id'])) {
            $query->where('fuel_tank_id', $validated['fuel_tank_id']);
        }

        if (! empty($validated['machine_id'])) {
            $query->where('machine_id', $validated['machine_id']);
        }

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $query->whereBetween('transaction_date', [
                $validated['start_date'],
                $validated['end_date'],
            ]);
        }

        if (! empty($validated['supplier'])) {
            $query->where('supplier', 'like', '%'.$validated['supplier'].'%');
        }

        $transactions = $query->latest('transaction_date')->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Create new fuel transaction
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fuel_tank_id' => 'nullable|exists:fuel_tanks,id',
            'machine_id' => 'nullable|exists:machines,id',
            'transaction_type' => 'required|in:refill,dispensing,delivery,transfer,adjustment,theft,spillage',
            'quantity_liters' => 'required|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'fuel_type' => 'required|string|in:diesel,petrol,biodiesel,lpg,cng,electric',
            'transaction_date' => 'nullable|date',
            'odometer_reading' => 'nullable|numeric|min:0',
            'machine_hours' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'from_tank_id' => 'nullable|required_if:transaction_type,transfer|exists:fuel_tanks,id',
            'to_tank_id' => 'nullable|required_if:transaction_type,transfer|exists:fuel_tanks,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['team_id'] = $request->user()->currentTeam->id;
        $data['user_id'] = $request->user()->id;
        $data['transaction_date'] = $data['transaction_date'] ?? now();

        // Calculate total cost if not provided
        if (! isset($data['total_cost']) && isset($data['unit_price'])) {
            $data['total_cost'] = $data['quantity_liters'] * $data['unit_price'];
        }

        // Handle receipt file upload — store on private disk so files are not web-accessible
        if ($request->hasFile('receipt_file')) {
            $path = $request->file('receipt_file')->store('fuel-receipts', 'local');
            $data['receipt_file_path'] = $path;
        }

        // Use service to record transaction (handles tank updates and alerts)
        $transaction = $this->fuelService->recordTransaction($data);

        return response()->json($transaction, 201);
    }

    /**
     * Get single fuel transaction
     */
    public function show(Request $request, FuelTransaction $fuelTransaction): JsonResponse
    {
        // Authorization check
        if ($fuelTransaction->team_id !== $request->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $fuelTransaction->load(['fuelTank', 'machine', 'user', 'fromTank', 'toTank']);

        return response()->json($fuelTransaction);
    }

    /**
     * Update fuel transaction
     */
    public function update(Request $request, FuelTransaction $fuelTransaction): JsonResponse
    {
        // Authorization check
        if ($fuelTransaction->team_id !== $request->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'unit_price' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'transaction_date' => 'nullable|date',
            'odometer_reading' => 'nullable|numeric|min:0',
            'machine_hours' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fuelTransaction->update($validator->validated());

        return response()->json($fuelTransaction->load(['fuelTank', 'machine', 'user']));
    }

    /**
     * Delete fuel transaction
     */
    public function destroy(Request $request, FuelTransaction $fuelTransaction): JsonResponse
    {
        // Authorization check
        if ($fuelTransaction->team_id !== $request->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Note: Deleting might affect tank levels - consider reverting the transaction
        // For now, we'll just delete the record

        if ($fuelTransaction->receipt_file_path) {
            Storage::disk('local')->delete($fuelTransaction->receipt_file_path);
        }

        $fuelTransaction->delete();

        return response()->json(['message' => 'Fuel transaction deleted successfully']);
    }

    /**
     * Get transaction statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam->id;
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        $startDate = $validated['start_date'] ?? now()->subDays(30)->toDateString();
        $endDate = $validated['end_date'] ?? now()->toDateString();

        $analytics = $this->fuelService->getTeamAnalytics($teamId, $startDate, $endDate);

        return response()->json($analytics);
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $teamId = $request->user()->currentTeam->id;

        $query = FuelTransaction::where('team_id', $teamId)
            ->with(['fuelTank:id,name', 'machine:id,name', 'user:id,name']);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date,
                $request->end_date,
            ]);
        }

        $transactions = $query->latest('transaction_date')->limit(5000)->get();

        $filename = 'fuel-transactions-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'Date', 'Type', 'Tank', 'Machine', 'Fuel Type', 'Quantity (L)',
                'Unit Price', 'Total Cost', 'Supplier', 'Invoice', 'User', 'Notes',
            ]);

            // Data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->transaction_date->format('Y-m-d H:i:s'),
                    $transaction->transaction_type,
                    $transaction->fuelTank->name ?? 'N/A',
                    $transaction->machine->name ?? 'N/A',
                    $transaction->fuel_type,
                    $transaction->quantity_liters,
                    $transaction->unit_price,
                    $transaction->total_cost,
                    $transaction->supplier,
                    $transaction->invoice_number,
                    $transaction->user->name,
                    $transaction->notes,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
