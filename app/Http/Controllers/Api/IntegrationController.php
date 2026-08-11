<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SyncIntegrationMachinesJob;
use App\Models\Integration;
use App\Services\Integration\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Integration API Controller
 *
 * Handles manufacturer API integrations (Volvo, CAT, etc.)
 */
class IntegrationController extends Controller
{
    public function __construct(protected IntegrationService $integrationService) {}

    /**
     * List all integrations for current team
     *
     * GET /api/integrations
     */
    public function index()
    {
        $this->authorize('viewAny', Integration::class);

        $integrations = Integration::where('team_id', auth()->user()->current_team_id)
            ->select('id', 'provider', 'name', 'status', 'last_sync_at', 'last_sync_status', 'machines_count', 'last_error')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $integrations,
        ]);
    }

    /**
     * Get a single integration
     *
     * GET /api/integrations/{id}
     */
    public function show(Integration $integration)
    {
        $this->authorize('view', $integration);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $integration->id,
                'provider' => $integration->provider,
                'name' => $integration->name,
                'status' => $integration->status,
                'last_sync_at' => $integration->last_sync_at,
                'last_sync_status' => $integration->last_sync_status,
                'machines_count' => $integration->machines_count,
                'last_error' => $integration->last_error,
                'created_at' => $integration->created_at,
                'updated_at' => $integration->updated_at,
            ],
        ]);
    }

    /**
     * Create a new integration
     *
     * POST /api/integrations
     */
    public function store(Request $request)
    {
        $this->authorize('create', Integration::class);

        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:volvo,cat,komatsu,bell,ctrack',
            'name' => 'required|string|max:255',
            'credentials' => 'required|array',
            'credentials.api_key' => 'required|string',
            'credentials.api_secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if integration for this provider already exists
        if (Integration::where('team_id', auth()->user()->current_team_id)
            ->where('provider', $request->provider)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => "Integration for {$request->provider} already exists",
            ], Response::HTTP_CONFLICT);
        }

        try {
            $integration = Integration::create([
                'team_id' => auth()->user()->current_team_id,
                'provider' => $request->provider,
                'name' => $request->name,
                'credentials' => $request->credentials,
                'status' => 'disconnected',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Integration created successfully',
                'data' => [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'name' => $integration->name,
                    'status' => $integration->status,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('Failed to create integration', ['provider' => $request->provider, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create the integration. Please check the credentials and try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update an integration
     *
     * PUT /api/integrations/{id}
     */
    public function update(Request $request, Integration $integration)
    {
        $this->authorize('update', $integration);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'credentials' => 'sometimes|array',
            'credentials.api_key' => 'string',
            'credentials.api_secret' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $integration->update($request->only('name', 'credentials'));

            return response()->json([
                'success' => true,
                'message' => 'Integration updated successfully',
                'data' => [
                    'id' => $integration->id,
                    'name' => $integration->name,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update integration', ['integration_id' => $integration->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update the integration. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete an integration
     *
     * DELETE /api/integrations/{id}
     */
    public function destroy(Integration $integration)
    {
        $this->authorize('delete', $integration);

        try {
            $integration->delete();

            return response()->json([
                'success' => true,
                'message' => 'Integration deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete integration', ['integration_id' => $integration->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete the integration. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Test connection to manufacturer API
     *
     * POST /api/integrations/{id}/test
     */
    public function test(Integration $integration)
    {
        $this->authorize('test', $integration);

        $result = $this->integrationService->testConnection($integration);

        // Update status based on test result
        if ($result['success']) {
            $integration->update(['status' => 'connected']);
        } else {
            $integration->update(['status' => 'error', 'last_error' => $result['error'] ?? null]);
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? $result['error'] ?? 'Connection test completed',
        ]);
    }

    /**
     * Trigger manual sync for integration
     *
     * POST /api/integrations/{id}/sync
     */
    public function sync(Integration $integration)
    {
        $this->authorize('sync', $integration);

        try {
            SyncIntegrationMachinesJob::dispatch($integration);

            return response()->json([
                'success' => true,
                'message' => 'Sync job dispatched successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch integration sync', ['integration_id' => $integration->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start the sync. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get machines synced from this integration
     *
     * GET /api/integrations/{id}/machines
     */
    public function machines(Integration $integration)
    {
        $this->authorize('view', $integration);

        $machines = $integration->machines()
            ->select('id', 'name', 'model', 'status', 'manufacturer', 'latitude', 'longitude')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $machines,
        ]);
    }

    /**
     * Get available manufacturers
     *
     * GET /api/integrations/manufacturers
     */
    public function manufacturers()
    {
        return response()->json([
            'success' => true,
            'data' => $this->integrationService->getAvailableManufacturers(),
        ]);
    }
}
