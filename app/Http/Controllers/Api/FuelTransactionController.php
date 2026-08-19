<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelTransaction;
use App\Services\FuelManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FuelTransactionController extends Controller
{
    public function __construct(
        protected FuelManagementService $fuelService
    ) {}

    /**
     * Get all fuel transactions for team
     */
    public function index(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;

        $query = FuelTransaction::where('team_id', $teamId)
            ->with(['fuelTank:id,name', 'machine:id,name', 'user:id,name']);

        // Filters
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->has('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        if ($request->has('fuel_tank_id')) {
            $query->where('fuel_tank_id', $request->fuel_tank_id);
        }

        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date,
                $request->end_date,
            ]);
        }

        if ($request->has('supplier')) {
            $query->where('supplier', 'like', "%{$request->supplier}%");
        }

        $transactions = $query->latest('transaction_date')->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Create new fuel transaction
     */
    public function store(Request $request)
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

        // Handle receipt file upload
        if ($request->hasFile('receipt_file')) {
            $path = $request->file('receipt_file')->store('fuel-receipts', 'public');
            $data['receipt_file_path'] = $path;
        }

        // Use service to record transaction (handles tank updates and alerts)
        $transaction = $this->fuelService->recordTransaction($data);

        return response()->json($transaction, 201);
    }

    /**
     * Get single fuel transaction
     */
    public function show(Request $request, FuelTransaction $fuelTransaction)
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
    public function update(Request $request, FuelTransaction $fuelTransaction)
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
    public function destroy(Request $request, FuelTransaction $fuelTransaction)
    {
        // Authorization check
        if ($fuelTransaction->team_id !== $request->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Note: Deleting might affect tank levels - consider reverting the transaction
        // For now, we'll just delete the record

        if ($fuelTransaction->receipt_file_path) {
            Storage::disk('public')->delete($fuelTransaction->receipt_file_path);
        }

        $fuelTransaction->delete();

        return response()->json(['message' => 'Fuel transaction deleted successfully']);
    }

    /**
     * Get transaction statistics
     */
    public function statistics(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;
        $startDate = $request->input('start_date', now()->subDays(30));
        $endDate = $request->input('end_date', now());

        $analytics = $this->fuelService->getTeamAnalytics($teamId, $startDate, $endDate);

        return response()->json($analytics);
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request)
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

        $transactions = $query->latest('transaction_date')->get();

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
