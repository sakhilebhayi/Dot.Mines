<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelTransaction;
use App\Models\User;
use App\Services\FuelManagementService;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        $teamId = $this->currentTeamId($request);

        $query = FuelTransaction::where('team_id', $teamId)
            ->with(['fuelTank:id,name', 'machine:id,name', 'user:id,name']);

        // Filters
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->input('transaction_type'));
        }

        if ($request->has('fuel_type')) {
            $query->where('fuel_type', $request->input('fuel_type'));
        }

        if ($request->has('fuel_tank_id')) {
            $query->where('fuel_tank_id', $request->input('fuel_tank_id'));
        }

        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        if ($request->has('supplier')) {
            $query->where('supplier', 'like', "%{$request->input('supplier')}%");
        }

        $transactions = $query->latest('transaction_date')->paginate(50);

        return ApiResponse::paginated($transactions);
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
        $data['team_id'] = $this->currentTeamId($request);
        $data['user_id'] = CurrentUser::get()?->id;
        /** @psalm-suppress MixedAssignment */
        $data['transaction_date'] = $data['transaction_date'] ?? now();

        // Calculate total cost if not provided
        if (! isset($data['total_cost']) && isset($data['unit_price'])) {
            $data['total_cost'] = (float) $data['quantity_liters'] * (float) $data['unit_price'];
        }

        // Handle receipt file upload
        $receiptFile = $request->file('receipt_file');
        if ($receiptFile instanceof UploadedFile) {
            $data['receipt_file_path'] = $receiptFile->store('fuel-receipts', 'public');
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
        if ($fuelTransaction->team_id !== $this->currentTeamId($request)) {
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
        if ($fuelTransaction->team_id !== $this->currentTeamId($request)) {
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
        if ($fuelTransaction->team_id !== $this->currentTeamId($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Note: Deleting might affect tank levels - consider reverting the transaction
        // For now, we'll just delete the record

        if (($fuelTransaction->receipt_file_path !== null && $fuelTransaction->receipt_file_path !== '' && $fuelTransaction->receipt_file_path !== '0')) {
            Storage::disk('public')->delete($fuelTransaction->receipt_file_path);
        }

        $fuelTransaction->delete();

        return response()->json(['message' => 'Fuel transaction deleted successfully']);
    }

    /**
     * Get transaction statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $teamId = $this->currentTeamId($request);
        /** @psalm-suppress MixedAssignment */
        $startRaw = $request->input('start_date');
        /** @psalm-suppress MixedAssignment */
        $endRaw = $request->input('end_date');
        $startDate = Carbon::parse(is_string($startRaw) ? $startRaw : now()->subDays(30)->toDateTimeString());
        $endDate = Carbon::parse(is_string($endRaw) ? $endRaw : now()->toDateTimeString());

        $analytics = $this->fuelService->getTeamAnalytics($teamId, $startDate, $endDate);

        return response()->json($analytics);
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $teamId = $this->currentTeamId($request);

        $query = FuelTransaction::where('team_id', $teamId)
            ->with(['fuelTank:id,name', 'machine:id,name', 'user:id,name']);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        $transactions = $query->latest('transaction_date')->get();

        $filename = 'fuel-transactions-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($transactions): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                return;
            }

            // Headers
            fputcsv($file, [
                'Date', 'Type', 'Tank', 'Machine', 'Fuel Type', 'Quantity (L)',
                'Unit Price', 'Total Cost', 'Supplier', 'Invoice', 'User', 'Notes',
            ]);

            // Data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->transaction_date?->format('Y-m-d H:i:s') ?? '',
                    $transaction->transaction_type,
                    $transaction->fuelTank?->name ?? 'N/A',
                    $transaction->machine?->name ?? 'N/A',
                    $transaction->fuel_type,
                    $transaction->quantity_liters,
                    $transaction->unit_price,
                    $transaction->total_cost,
                    $transaction->supplier,
                    $transaction->invoice_number,
                    $transaction->user?->name,
                    $transaction->notes,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
