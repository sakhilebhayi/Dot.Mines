<?php

namespace App\Providers;

use App\Console\Commands\ScanBladeUnescaped;
use App\Events\FeedCommentCreated;
use App\Events\FeedPostCreated;
use App\Events\FeedPostStatusChanged;
use App\Listeners\NotifyOnJobFailed;
use App\Listeners\SendFeedApprovalNotification;
use App\Listeners\SendFeedCommentNotification;
use App\Listeners\SendFeedPostNotification;
use App\Mail\WelcomeMail;
use App\Models\AuditLog;
use App\Models\MaintenanceRecord;
use App\Observers\MaintenanceRecordObserver;
use App\Services\AuditService;
use App\Services\RealtimeEventScheduler;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Detect N+1 queries in non-production environments
        if (! $this->app->environment('production')) {
            Model::preventLazyLoading();
        }

        // Register console commands so scanning is available in CI
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanBladeUnescaped::class,
            ]);
        }

        // Enforce enterprise password policy (min 12 chars, mixed case, numbers, symbols)
        $this->configurePasswordPolicy();

        // Configure rate limiting
        $this->configureRateLimiting();

        // Register real-time event scheduling
        $this->app->booted(function () {
            $schedule = $this->app->make('Illuminate\Console\Scheduling\Schedule');
            RealtimeEventScheduler::register($schedule);
        });

        // Send welcome email when users register
        Event::listen(Registered::class, function (Registered $event) {
            try {
                Mail::to($event->user->email)->queue(new WelcomeMail($event->user));
            } catch (\Exception $e) {
                Log::error('Failed to queue welcome email', ['user_id' => $event->user->id, 'error' => $e->getMessage()]);
            }
        });

        // Sync machine status when maintenance records are created/updated
        MaintenanceRecord::observe(MaintenanceRecordObserver::class);

        // Pulse dashboard access (admins only in non-local)
        Gate::define('viewPulse', function ($user) {
            $admins = array_filter(array_map('trim', explode(',', (string) env('HORIZON_ADMINS', ''))));

            return in_array($user->email, $admins, true)
                || $user->hasTeamRole($user->currentTeam, 'admin');
        });

        // Feed notification listeners
        Event::listen(FeedPostCreated::class, SendFeedPostNotification::class);
        Event::listen(FeedCommentCreated::class, SendFeedCommentNotification::class);
        Event::listen(FeedPostStatusChanged::class, SendFeedApprovalNotification::class);

        // Listen for failed queue jobs and notify monitoring
        Event::listen(JobFailed::class, function ($event) {
            try {
                $listener = new NotifyOnJobFailed;
                $listener->handle($event);
            } catch (\Throwable $e) {
                Log::error('Failed to notify on job failure', ['error' => $e->getMessage()]);
            }
        });

        // ── Auth audit events ──────────────────────────────────────────────
        Event::listen(Login::class, function ($event) {
            AuditService::log(
                AuditLog::LOGIN_SUCCESS,
                'Successful login',
                $event->user,
                ['guard' => $event->guard],
                $event->user->id,
                $event->user->current_team_id
            );
        });

        Event::listen(Failed::class, function ($event) {
            AuditService::log(
                AuditLog::LOGIN_FAILED,
                'Failed login attempt for: '.($event->credentials['email'] ?? 'unknown'),
                null,
                ['email' => $event->credentials['email'] ?? 'unknown', 'guard' => $event->guard],
                $event->user?->id,
                $event->user?->current_team_id
            );
        });

        Event::listen(Lockout::class, function ($event) {
            AuditService::log(
                AuditLog::LOGIN_LOCKOUT,
                'Account locked out due to too many failed login attempts',
                null,
                ['email' => $event->request->input('email', 'unknown')],
                null,
                null,
                $event->request->ip()
            );
        });

        Event::listen(Logout::class, function ($event) {
            AuditService::log(
                AuditLog::LOGOUT,
                'User logged out',
                $event->user,
                ['guard' => $event->guard],
                $event->user->id,
                $event->user->current_team_id
            );
        });

        // Configure Sentry release/environment if present
        try {
            if (env('SENTRY_DSN')) {
                if (function_exists('\Sentry\configureScope')) {
                    \Sentry\configureScope(function ($scope): void {
                        $env = env('SENTRY_ENVIRONMENT');
                        $release = env('SENTRY_RELEASE');
                        if ($env) {
                            $scope->setTag('environment', $env);
                        }
                        if ($release) {
                            $scope->setTag('release', $release);
                        }
                    });
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to configure Sentry release/environment', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // API rate limiting - 60 requests per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many requests. Please try again later.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Login rate limiting - 5 attempts per minute
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->email.'|'.$request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Webhook endpoints - higher limit for integrations (120 per minute)
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Webhook rate limit exceeded.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Reports generation - lower limit due to resource intensity (10 per minute)
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Report generation rate limit exceeded.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Signed downloads - protect large or sensitive file downloads
        RateLimiter::for('downloads', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Download rate limit exceeded.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // File uploads — prevent upload flooding (10 per minute per user)
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Upload rate limit exceeded. Please wait before uploading again.',
                        'retry_after' => 60,
                    ], 429);
                });
        });

        // Feed posting — prevent post spam (20 per minute per user)
        RateLimiter::for('feed-post', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Post rate limit exceeded. Please slow down.',
                        'retry_after' => 60,
                    ], 429);
                });
        });
    }

    /**
     * Enforce enterprise-grade password strength requirements.
     *
     * Applied to both registration and password changes via PasswordValidationRules trait.
     * In production, passwords are also checked against known data-breach lists.
     */
    protected function configurePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            // In production: reject passwords from known breach dumps (HIBP API)
            return app()->environment('production') ? $rule->uncompromised() : $rule;
        });
    }
}
