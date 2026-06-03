<?php

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

// Test session routes are restricted to non-production local environments only.
if (app()->environment('local') && config('app.debug')) {
    require __DIR__.'/test-session.php';
}

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Sitemap
Route::get('/sitemap.xml', function () {
    $urls = [
        ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['loc' => route('login'), 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['loc' => route('register'), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => route('terms.show'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ['loc' => route('policy.show'), 'changefreq' => 'yearly', 'priority' => '0.3'],
    ];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>'.e($url['loc'])."</loc>\n";
        $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
        $xml .= "    <priority>{$url['priority']}</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= '</urlset>';

    return Response::make($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

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
    Route::get('/fleet/replay', App\Livewire\FleetMovementReplay::class)
        ->name('fleet.replay');

    Route::get('/fleet/route-planning', App\Livewire\RoutePlanning::class)
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

    Route::get('/mine-areas/{mineArea}', App\Livewire\MineAreaDetail::class)
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
    Route::get('/reports/{report}/download', [\App\Http\Controllers\ReportDownloadController::class, 'download'])
        ->middleware(['auth', 'throttle:downloads'])
        ->name('reports.signed-download');

    // Signed mine plan download route (mirror reports signed-download)
    Route::get('/mine-plans/{minePlan}/download', [\App\Http\Controllers\MinePlanDownloadController::class, '__invoke'])
        ->middleware(['auth', 'throttle:downloads'])
        ->name('mineplans.signed-download');

    // Reports view 2 (scope selectors) — must come BEFORE the {report} param route
    Route::get('/reports/view-2', [App\Http\Controllers\ReportController::class, 'view2'])->name('reports.view2');
    // Simple generate endpoint (GET form) — must come BEFORE the {report} param route
    Route::get('/reports/generate/simple', [App\Http\Controllers\ReportController::class, 'generate'])->name('reports.generate');

    Route::get('/reports/{report}', function (Report $report) {
        // Ensure the user belongs to the same team as this report.
        abort_unless(
            Auth::user()->current_team_id === $report->team_id,
            403
        );

        return view('reports.show', ['report' => $report]);
    })->name('reports.show');

    // Alerts
    Route::get('/alerts', App\Livewire\Alerts::class)
        ->name('alerts');

    // Production Dashboard
    Route::get('/production', App\Livewire\ProductionDashboard::class)
        ->name('production');

    // Fuel Management
    Route::get('/fuel', App\Livewire\FuelManagement::class)
        ->name('fuel');

    // Maintenance & Health
    Route::get('/maintenance', App\Livewire\MaintenanceDashboard::class)
        ->name('maintenance');

    // AI Optimization Center
    Route::get('/ai-optimization', App\Livewire\AIOptimizationDashboard::class)
        ->name('ai-optimization');
    Route::get('/ai-analytics', App\Livewire\AIAnalytics::class)
        ->name('ai-analytics');

    // Documentation
    Route::get('/documentation', App\Livewire\Documentation::class)
        ->name('documentation');

    // Integrations
    Route::get('/integrations', function () {
        return view('integrations.index');
    })->name('integrations');

    Route::get('/integrations/{integration}', function (App\Models\Integration $integration) {
        // Verify the authenticated user belongs to the same team as this integration.
        abort_unless(
            Auth::user()->current_team_id === $integration->team_id,
            403
        );

        return view('integrations.show', ['integration' => $integration]);
    })->name('integrations.show');

    // Billing & Subscriptions
    Route::get('/billing', App\Livewire\BillingPortal::class)
        ->name('billing.index');

    Route::get('/billing/success', function (\Illuminate\Http\Request $request) {
        $reference = $request->query('reference') ?? $request->query('trxref');
        if ($reference) {
            // Paystack redirects here after payment. Subscription is processed via webhook;
            // we just confirm the redirect and show a friendly message.
            return redirect()->route('billing.index')
                ->with('success', 'Payment received! Your subscription will be activated shortly.');
        }

        return redirect()->route('billing.index')
            ->with('success', 'Subscription activated successfully!');
    })->name('billing.success');

    // Feed
    Route::get('/feed', App\Livewire\Feed::class)
        ->name('feed');

    // Feed attachment file serving — streams binary blobs stored in the DB.
    // Must come before /feed/admin to avoid route collision.
    Route::get('/feed/attachments/{attachment}', [App\Http\Controllers\FeedAttachmentController::class, 'serve'])
        ->middleware('throttle:downloads')
        ->name('feed.attachment.serve');

    // Feed admin panel — restricted to admin role.
    Route::get('/feed/admin', App\Livewire\FeedAdminPanel::class)
        ->middleware('admin')
        ->name('feed.admin');

    // WhatsApp migration dashboard — restricted to admin role.
    Route::get('/feed/migration', App\Livewire\WhatsAppMigration::class)
        ->middleware('admin')
        ->name('feed.migration');

    // Shift Templates management (admin/supervisor UI)
    Route::get('/shift-templates', App\Livewire\ShiftTemplateManager::class)
        ->name('shift-templates');

    // Settings
    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings');

    Route::get('/team/settings', function () {
        return view('team.settings');
    })->name('team.settings');
});

// Paystack Webhooks (HMAC-SHA512 signature verified inside controller; rate limited by IP)
Route::post('/webhooks/paystack', [App\Http\Controllers\WebhookController::class, 'handlePaystack'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.paystack');

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
Route::post('/livewire/update', [\Livewire\Mechanisms\HandleRequests\HandleRequests::class, 'handleUpdate'])
    ->middleware('web')
    ->name('default.livewire.update');
