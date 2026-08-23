<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\FuelTankController;
use App\Http\Controllers\Api\FuelTransactionController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\MachineAssignmentController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\MachineHealthController;
use App\Http\Controllers\Api\MaintenanceRecordController;
use App\Http\Controllers\Api\MaintenanceScheduleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\WebhookEndpointController;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Version 1 of the API -- the endpoints themselves, with no version in them.
 *
 * This file is registered TWICE by routes/api.php: once under `/api/v1` and
 * once bare at `/api`, both with the same middleware stack. Defining the
 * endpoints once is what guarantees the two spellings can never drift apart,
 * which matters more than it looks -- the bare paths are what every existing
 * integration already calls.
 *
 * Add an endpoint here and it exists at both spellings automatically. Do not
 * add routes to routes/api.php; that file is the composition root.
 *
 * When v2 arrives it gets its own file, and the bare paths stay pointed at
 * this one. See routes/api.php for why.
 */

/**
 * Incremental sync (hybrid architecture Slice 1): versioned deltas for the
 * browser's local cache. GET /api/v1/sync?since=<cursor>&scopes=...
 */
Route::get('/sync', SyncController::class)->name('sync');

/**
 * User & Auth endpoints
 */
Route::get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/user/team/{team_id}', function (Request $request, string $team_id) {
    $user = $request->user();

    if (! $user instanceof User) {
        abort(401);
    }

    $team = Team::query()->findOrFail((int) $team_id);

    if (! $user->belongsToTeam($team)) {
        abort(403, 'You do not belong to this team.');
    }

    $user->update(['current_team_id' => $team->id]);

    return response()->json(['message' => 'Team switched successfully']);
});

/**
 * Machine endpoints
 */
Route::prefix('machines')->group(function () {
    Route::get('/', [MachineController::class, 'index'])
        ->middleware('cache.headers:short');                           // List machines (1 min cache)
    Route::post('/', [MachineController::class, 'store']);             // Create machine
    Route::get('/{machine}', [MachineController::class, 'show'])
        ->middleware('cache.headers:medium');                          // Get single machine (5 min cache)
    Route::put('/{machine}', [MachineController::class, 'update']);    // Update machine
    Route::delete('/{machine}', [MachineController::class, 'destroy']); // Delete machine

    // Machine sub-resources
    Route::get('/{machine}/metrics', [MachineController::class, 'metrics'])
        ->middleware('cache.headers:short');                           // Get metrics (1 min cache)
    Route::post('/{machine}/location', [MachineController::class, 'updateLocation']);  // Update location
    Route::get('/{machine}/alerts', [MachineController::class, 'alerts'])
        ->middleware('cache.headers:short');                           // Get active alerts (1 min cache)
});

/**
 * Geofence endpoints
 */
Route::prefix('geofences')->group(function () {
    Route::get('/', [GeofenceController::class, 'index']);              // List geofences
    Route::post('/', [GeofenceController::class, 'store']);             // Create geofence
    Route::get('/{geofence}', [GeofenceController::class, 'show']);     // Get single geofence
    Route::put('/{geofence}', [GeofenceController::class, 'update']);   // Update geofence
    Route::delete('/{geofence}', [GeofenceController::class, 'destroy']); // Delete geofence

    // Geofence sub-resources
    Route::get('/{geofence}/entries', [GeofenceController::class, 'entries']);           // Get entries
    Route::get('/{geofence}/tonnage-stats', [GeofenceController::class, 'tonnageStats']); // Get tonnage stats
    Route::get('/{geofence}/active-machines', [GeofenceController::class, 'activeMachines']); // Active machines
});

/**
 * Alert endpoints
 */
Route::prefix('alerts')->group(function () {
    Route::get('/', [AlertController::class, 'index']);                // List alerts
    Route::post('/', [AlertController::class, 'store']);               // Create alert
    Route::get('/{alert}', [AlertController::class, 'show']);          // Get single alert
    Route::post('/{alert}/acknowledge', [AlertController::class, 'acknowledge']); // Acknowledge
    Route::post('/{alert}/resolve', [AlertController::class, 'resolve']);        // Resolve

    // Alert statistics
    Route::get('/stats/active', [AlertController::class, 'activeCount']);        // Active count
    Route::get('/machine/{machineId}', [AlertController::class, 'machineAlerts']); // Machine alerts
});

/**
 * Integration endpoints
 */
Route::prefix('integrations')->group(function () {
    Route::get('/', [IntegrationController::class, 'index']);          // List integrations
    Route::post('/', [IntegrationController::class, 'store']);         // Create integration
    Route::get('/{integration}', [IntegrationController::class, 'show']); // Get single
    Route::put('/{integration}', [IntegrationController::class, 'update']); // Update
    Route::delete('/{integration}', [IntegrationController::class, 'destroy']); // Delete

    // Integration actions
    Route::post('/{integration}/test', [IntegrationController::class, 'test']);   // Test connection
    Route::post('/{integration}/sync', [IntegrationController::class, 'sync']);   // Trigger sync
    Route::get('/{integration}/machines', [IntegrationController::class, 'machines']); // Get machines
});

/**
 * Report endpoints (with stricter rate limiting on generation)
 */
Route::prefix('reports')->group(function () {
    Route::get('/', [ReportController::class, 'index']);               // List reports
    Route::post('/', [ReportController::class, 'generate'])            // Generate report
        ->middleware('throttle:reports');
    Route::get('/{report}', [ReportController::class, 'show']);        // Get single report
    Route::delete('/{report}', [ReportController::class, 'destroy']);  // Delete report

    // Report actions
    Route::get('/{report}/download', [ReportController::class, 'download'])
        ->middleware('throttle:downloads'); // Download file
    Route::get('/templates', [ReportController::class, 'templates']);        // Get templates
    Route::get('/stats', [ReportController::class, 'stats']);                // Get stats
});

/**
 * Machine Assignment endpoints
 */
Route::prefix('assignments')->group(function () {
    Route::get('/available', [MachineAssignmentController::class, 'available']);
    Route::get('/machines/{machine}/history', [MachineAssignmentController::class, 'history']);
});

/**
 * Notification endpoints (real-time alerts)
 */
Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);              // List notifications with filtering
    Route::get('/unread', [NotificationController::class, 'unread']);       // Get user's unread notifications
    Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']); // Mark single as read
    Route::put('/batch-read', [NotificationController::class, 'markMultipleAsRead']); // Batch mark as read
    Route::get('/stats', [NotificationController::class, 'stats']);         // Get alert statistics
    Route::delete('/', [NotificationController::class, 'clear']);           // Bulk delete old notifications
});

/**
 * Fuel Management endpoints
 */
Route::prefix('fuel')->group(function () {
    // Fuel Tanks
    Route::prefix('tanks')->group(function () {
        Route::get('/', [FuelTankController::class, 'index']);
        Route::post('/', [FuelTankController::class, 'store']);
        Route::get('/{fuelTank}', [FuelTankController::class, 'show']);
        Route::put('/{fuelTank}', [FuelTankController::class, 'update']);
        Route::delete('/{fuelTank}', [FuelTankController::class, 'destroy']);
        Route::get('/{fuelTank}/statistics', [FuelTankController::class, 'statistics']);
    });

    // Fuel Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [FuelTransactionController::class, 'index']);
        Route::post('/', [FuelTransactionController::class, 'store']);
        Route::get('/statistics', [FuelTransactionController::class, 'statistics']);
        Route::get('/export', [FuelTransactionController::class, 'export']);
        Route::get('/{fuelTransaction}', [FuelTransactionController::class, 'show']);
        Route::put('/{fuelTransaction}', [FuelTransactionController::class, 'update']);
        Route::delete('/{fuelTransaction}', [FuelTransactionController::class, 'destroy']);
    });
});

/**
 * Maintenance & Health endpoints
 */
Route::prefix('maintenance')->group(function () {
    // Machine Health
    Route::prefix('health')->group(function () {
        Route::get('/', [MachineHealthController::class, 'index']);
        Route::get('/statistics', [MachineHealthController::class, 'statistics']);
        Route::get('/{machine}', [MachineHealthController::class, 'show']);
        Route::put('/{machine}', [MachineHealthController::class, 'update']);
        Route::post('/{machine}/diagnostic', [MachineHealthController::class, 'diagnostic']);
    });

    // Maintenance Schedules
    Route::prefix('schedules')->group(function () {
        Route::get('/', [MaintenanceScheduleController::class, 'index']);
        Route::post('/', [MaintenanceScheduleController::class, 'store']);
        Route::get('/due', [MaintenanceScheduleController::class, 'dueSchedules']);
        Route::get('/{schedule}', [MaintenanceScheduleController::class, 'show']);
        Route::put('/{schedule}', [MaintenanceScheduleController::class, 'update']);
        Route::delete('/{schedule}', [MaintenanceScheduleController::class, 'destroy']);
        Route::post('/{machine}/check', [MaintenanceScheduleController::class, 'checkSchedules']);
    });

    // Maintenance Records (Work Orders)
    Route::prefix('records')->group(function () {
        Route::get('/', [MaintenanceRecordController::class, 'index']);
        Route::post('/', [MaintenanceRecordController::class, 'store']);
        Route::get('/analytics', [MaintenanceRecordController::class, 'analytics']);
        Route::get('/export', [MaintenanceRecordController::class, 'export']);
        Route::get('/{record}', [MaintenanceRecordController::class, 'show']);
        Route::put('/{record}', [MaintenanceRecordController::class, 'update']);
        Route::post('/{record}/complete', [MaintenanceRecordController::class, 'complete']);
        Route::delete('/{record}', [MaintenanceRecordController::class, 'destroy']);
    });
});

/**
 * Outbound webhook endpoints: where this team wants events pushed.
 */
Route::prefix('webhooks')->group(function () {
    Route::get('/', [WebhookEndpointController::class, 'index']);          // List endpoints
    Route::post('/', [WebhookEndpointController::class, 'store']);         // Create (returns the secret once)
    Route::get('/{webhook}', [WebhookEndpointController::class, 'show']);  // Get single
    Route::put('/{webhook}', [WebhookEndpointController::class, 'update']); // Update
    Route::delete('/{webhook}', [WebhookEndpointController::class, 'destroy']); // Delete

    Route::get('/{webhook}/deliveries', [WebhookEndpointController::class, 'deliveries']); // Recent deliveries
    Route::post('/{webhook}/test', [WebhookEndpointController::class, 'test']);            // Send a ping
});

/**
 * Live Location endpoint (real-time)
 */
Route::get('/live-locations', function () {
    $machines = Machine::select([
        'id', 'name', 'machine_type', 'status',
        'last_location_latitude', 'last_location_longitude', 'last_location_update',
    ])
        ->whereNotNull('last_location_latitude')
        ->get();

    return response()->json([
        'data' => $machines,
    ]);
});
