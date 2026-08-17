<?php

use App\Http\Controllers\EmailUnsubscribeController;
use App\Http\Controllers\FeedAttachmentController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MinePlanDownloadController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\RealtimeHealthController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportDownloadController;
use App\Http\Controllers\TermsOfServiceController;
use App\Http\Controllers\WebhookController;
use App\Livewire\AIAnalytics;
use App\Livewire\AIOptimizationDashboard;
use App\Livewire\Alerts;
use App\Livewire\BillingPortal;
use App\Livewire\Documentation;
use App\Livewire\Feed;
use App\Livewire\FeedAdminPanel;
use App\Livewire\FleetMovementReplay;
use App\Livewire\FuelManagement;
use App\Livewire\MachineAssignmentManager;
use App\Livewire\MaintenanceDashboard;
use App\Livewire\MineAreaDetail;
use App\Livewire\OperatorFatigueTracker;
use App\Livewire\ProductionDashboard;
use App\Livewire\RoutePlanning;
use App\Livewire\ShiftTemplateManager;
use App\Livewire\WhatsAppMigration;
use App\Models\Geofence;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

// Test session routes are restricted to non-production local environments only.
if (app()->environment('local') && config('app.debug')) {
    require __DIR__.'/test-session.php';
}

// Override Jetstream's terms/policy controllers to use safe markdown rendering
Route::get('/terms-of-service', [TermsOfServiceController::class, 'show'])->name('terms.show');
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'show'])->name('policy.show');
Route::view('/cookies', 'pages.cookies')->name('cookies');

// Health check — no auth, used by load balancers and monitoring services
// /health = liveness probe (full check: DB + cache + storage) — failure triggers pod restart
// /ready  = readiness probe (DB only, lightweight) — failure stops traffic routing without restart
Route::get('/health', HealthController::class)->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('health.ready');

// Real-time infrastructure health check for uptime monitors/load balancers.
Route::get('/up/realtime', [RealtimeHealthController::class, 'check'])
    ->name('health.realtime');

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

    Route::get('/mine-areas/{mineArea}/assignments', MachineAssignmentManager::class)
        ->name('mine-areas.assignments');

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

    // Reports view 2 (scope selectors) — must come BEFORE the {report} param route
    Route::get('/reports/view-2', [ReportController::class, 'view2'])->name('reports.view2');
    // Simple generate endpoint (GET form) — must come BEFORE the {report} param route
    Route::get('/reports/generate/simple', [ReportController::class, 'generate'])->name('reports.generate');

    Route::get('/reports/{report}', function (Report $report) {
        // Ensure the user belongs to the same team as this report.
        abort_unless(
            Auth::user()->current_team_id === $report->team_id,
            403
        );

        return view('reports.show', ['report' => $report]);
    })->name('reports.show');

    // Alerts
    Route::get('/alerts', Alerts::class)
        ->name('alerts');

    // Production Dashboard
    Route::get('/production', ProductionDashboard::class)
        ->name('production');

    Route::get('/operator-fatigue', OperatorFatigueTracker::class)
        ->name('operator-fatigue');

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

    Route::get('/integrations/{integration}', function (Integration $integration) {
        // Verify the authenticated user belongs to the same team as this integration.
        abort_unless(
            Auth::user()->current_team_id === $integration->team_id,
            403
        );

        return view('integrations.show', ['integration' => $integration]);
    })->name('integrations.show');

    // Billing & Subscriptions
    Route::get('/billing', BillingPortal::class)
        ->name('billing.index');

    Route::get('/billing/success', function (Request $request) {
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
    Route::get('/feed', Feed::class)
        ->name('feed');

    // Feed attachment file serving — streams binary blobs stored in the DB.
    // Must come before /feed/admin to avoid route collision.
    Route::get('/feed/attachments/{attachment}', [FeedAttachmentController::class, 'serve'])
        ->middleware('throttle:downloads')
        ->name('feed.attachment.serve');

    // Feed admin panel — restricted to admin role (2FA required).
    Route::get('/feed/admin', FeedAdminPanel::class)
        ->middleware(['admin', 'admin.2fa'])
        ->name('feed.admin');

    // WhatsApp migration dashboard — restricted to admin role (2FA required).
    Route::get('/feed/migration', WhatsAppMigration::class)
        ->middleware(['admin', 'admin.2fa'])
        ->name('feed.migration');

    // Shift Templates management (admin/supervisor UI)
    Route::get('/shift-templates', ShiftTemplateManager::class)
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
Route::post('/webhooks/paystack', [WebhookController::class, 'handlePaystack'])
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
Route::post('/livewire/update', [HandleRequests::class, 'handleUpdate'])
    ->middleware('web')
    ->name('default.livewire.update');

// ── Email Unsubscribe (POPIA § 45(2) / CAN-SPAM compliant) ────────────────
// These routes are intentionally public — no auth required. Signed URLs prevent abuse.
Route::get('/email/unsubscribe', [EmailUnsubscribeController::class, 'show'])
    ->name('email.unsubscribe');
Route::post('/email/unsubscribe', [EmailUnsubscribeController::class, 'handle'])
    ->name('email.unsubscribe.handle');
Route::view('/email/unsubscribed', 'emails.unsubscribed')
    ->name('email.unsubscribed');

// ── GDPR / Data Subject Rights ─────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->prefix('gdpr')->name('gdpr.')->group(function () {
    Route::get('/', [GdprController::class, 'index'])
        ->name('index');
    Route::post('/export', [GdprController::class, 'requestExport'])
        ->middleware('throttle:3,60')
        ->name('export');
    Route::get('/download/{token}', [GdprController::class, 'downloadExport'])
        ->name('download');
    Route::post('/delete', [GdprController::class, 'requestDeletion'])
        ->middleware('throttle:2,60')
        ->name('delete');
});
