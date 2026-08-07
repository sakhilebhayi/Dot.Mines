<?php

use App\Http\Controllers\MinePlanDownloadController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportDownloadController;
use App\Http\Controllers\WebhookController;
use App\Livewire\AIAnalytics;
use App\Livewire\AIOptimizationDashboard;
use App\Livewire\Alerts;
use App\Livewire\BillingPortal;
use App\Livewire\Documentation;
use App\Livewire\FleetMovementReplay;
use App\Livewire\FuelManagement;
use App\Livewire\MaintenanceDashboard;
use App\Livewire\MineAreaDetail;
use App\Livewire\ProductionDashboard;
use App\Livewire\RoutePlanning;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\Report;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

// Include test routes for session/CSRF debugging (remove in production)
if (config('app.debug')) {
    require __DIR__.'/test-session.php';
}

Route::get('/', function () {
    return view('welcome');
});

// Cookie Policy — Jetstream's termsAndPrivacyPolicy feature covers terms.show/policy.show
// natively. There's no Jetstream equivalent for a Cookie Policy, so this one is wired by hand,
// following the exact same Markdown-source convention.
Route::get('/cookies', function () {
    return view('cookies', [
        'cookies' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('cookies.md'))),
    ]);
})->name('cookies');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'ensure_team',
])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Fleet Management
    Route::get('/fleet', function () {
        return view('fleet.index');
    })->name('fleet');

    // Specific fleet routes must come before parameterized routes
    Route::get('/fleet/replay', FleetMovementReplay::class)
        ->name('fleet.replay');

    Route::get('/fleet/route-planning', RoutePlanning::class)
        ->name('fleet.route-planning');

    // Parameterized route comes last
    Route::get('/fleet/{machine}', function (Machine $machine) {
        return view('fleet.show', ['machine' => $machine]);
    })->name('fleet.show');

    // Live Map
    Route::get('/map', function () {
        return view('map.index');
    })->name('map');

    // Geofences
    Route::get('/geofences', function () {
        return view('geofences.index');
    })->name('geofences');

    Route::get('/geofences/{geofence}', function (Geofence $geofence) {
        return view('geofences.show', ['geofence' => $geofence]);
    })->name('geofences.show');

    // Mine Areas
    Route::get('/mine-areas', function () {
        return view('mine-areas.index');
    })->name('mine-areas');

    Route::get('/mine-areas/{mineArea}', MineAreaDetail::class)
        ->name('mine-areas.show');

    // Reports
    Route::get('/reports', function () {
        return view('reports.index');
    })->name('reports');

    // Live report generator (Livewire)
    Route::get('/reports/generate', function () {
        return view('reports.generate');
    })->name('report-generator');

    // Signed report download route (uses signed URLs created in emails)
    Route::get('/reports/{report}/download', [ReportDownloadController::class, 'download'])
        ->middleware(['auth', 'throttle:downloads'])
        ->name('reports.signed-download');

    // Signed mine plan download route (mirror reports signed-download)
    Route::get('/mine-plans/{minePlan}/download', [MinePlanDownloadController::class, '__invoke'])
        ->middleware(['auth', 'throttle:downloads'])
        ->name('mineplans.signed-download');

    Route::get('/reports/{report}', function (Report $report) {
        return view('reports.show', ['report' => $report]);
    })->name('reports.show');

    // Reports view 2 (scope selectors)
    Route::get('/reports/view-2', [ReportController::class, 'view2'])->name('reports.view2');
    // Simple generate endpoint (GET form) — moved to avoid path conflict with Livewire generator
    Route::get('/reports/generate/simple', [ReportController::class, 'generate'])->name('reports.generate');

    // Alerts
    Route::get('/alerts', Alerts::class)
        ->name('alerts');

    // Production Dashboard
    Route::get('/production', ProductionDashboard::class)
        ->name('production');

    // Fuel Management
    Route::get('/fuel', FuelManagement::class)
        ->name('fuel');

    // Maintenance & Health
    Route::get('/maintenance', MaintenanceDashboard::class)
        ->name('maintenance');

    // AI Optimization Center
    Route::get('/ai-optimization', AIOptimizationDashboard::class)
        ->name('ai-optimization');
    Route::get('/ai-analytics', AIAnalytics::class)
        ->name('ai-analytics');

    // Documentation
    Route::get('/documentation', Documentation::class)
        ->name('documentation');

    // Integrations
    Route::get('/integrations', function () {
        return view('integrations.index');
    })->name('integrations');

    Route::get('/integrations/{integration}', function () {
        return view('integrations.show');
    })->name('integrations.show');

    // Billing & Subscriptions
    Route::get('/billing', BillingPortal::class)
        ->name('billing.index');

    Route::get('/billing/success', function () {
        return redirect()->route('billing.index')->with('success', 'Subscription activated successfully!');
    })->name('billing.success');

    // Settings
    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings');

    Route::get('/team/settings', function () {
        return view('team.settings');
    })->name('team.settings');
});

// Stripe Webhooks (no auth required)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe'])
    ->name('webhooks.stripe');

// Public marketing/outer pages
Route::view('/features', 'pages.features')->name('features');
Route::view('/capabilities', 'pages.capabilities')->name('capabilities');
Route::view('/pricing', 'pages.pricing')->name('pricing');

// Core features detail pages
Route::prefix('core-features')->group(function () {
    Route::view('/fleet-tracking', 'pages.core-features.fleet-tracking')->name('core-features.fleet-tracking');
    Route::view('/maintenance', 'pages.core-features.maintenance')->name('core-features.maintenance');
    Route::view('/fuel', 'pages.core-features.fuel')->name('core-features.fuel');
});

// Ensure Livewire update route exists (helps when routes are cached or Livewire
// couldn't register its default route). This route name ends with
// "livewire.update" so Livewire will detect it as the update endpoint.
Route::post('/livewire/update', [HandleRequests::class, 'handleUpdate'])
    ->middleware('web')
    ->name('default.livewire.update');
