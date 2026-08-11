<?php

namespace App\Providers;

use App\Console\Commands\ScanBladeUnescaped;
use App\Listeners\NotifyOnJobFailed;
use App\Livewire\AINotifications;
use App\Mail\WelcomeMail;
use App\Services\RealtimeEventScheduler;
use App\Services\TeamRoleProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Events\TeamMemberUpdated;
use Livewire\Livewire;

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
        // Register console commands so scanning is available in CI
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanBladeUnescaped::class,
            ]);
        }

        // Livewire auto-derives a component's tag name from its class name via
        // Str::studly(), which turns "ai-notifications" into "AiNotifications"
        // (single-cap "Ai"), not "AINotifications" (the actual class, matching
        // this codebase's AI* naming convention -- AIAnalytics, AIOptimizationDashboard,
        // etc). On a case-insensitive filesystem (macOS) the autoloader finds the
        // file anyway and PHP's own class-name lookup is case-insensitive, so
        // this masked itself in local dev. On production's case-sensitive
        // filesystem the autoloader can't find "AiNotifications.php" and
        // <livewire:ai-notifications /> in navbar.blade.php throws
        // ComponentNotFoundException on every authenticated page. Registering
        // the name explicitly sidesteps the studly/kebab round-trip entirely.
        Livewire::component('ai-notifications', AINotifications::class);

        // Configure rate limiting
        $this->configureRateLimiting();

        // Password::default() (used by every Fortify password flow via
        // PasswordValidationRules::passwordRules() -- registration, password
        // update, password reset) falls back to Laravel's own bare minimum
        // (min 8 characters, nothing else) unless a default is registered
        // here. There was no composition requirement and no breach check
        // against known-leaked passwords at all before this.
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->mixedCase()->numbers()->symbols();

            // ->uncompromised() calls the (k-anonymity, privacy-preserving)
            // HaveIBeenPwned API -- a real network call on every password
            // submission, which has no place slowing down or flaking the
            // test suite.
            return $this->app->runningUnitTests() ? $rule : $rule->uncompromised();
        });

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

        // Jetstream's native team pages (teams.show) manage membership through
        // its own team_user pivot role and never touch our custom
        // roles/permissions tables, so a member added or role-changed there
        // previously had no rows in the RBAC system and was silently denied
        // every $this->authorize() check even though the team page showed
        // them with a real role. JetstreamServiceProvider registers the same
        // four role keys (admin/fleet_manager/operator/viewer) as
        // TeamRoleProvisioner's catalog, so this is a direct 1:1 sync -- on
        // every add, invitation acceptance (same AddTeamMember action), and
        // role update.
        Event::listen([TeamMemberAdded::class, TeamMemberUpdated::class], function ($event) {
            $pivotRole = $event->team->users()->find($event->user->id)?->membership?->role;

            if (! $pivotRole) {
                return;
            }

            try {
                TeamRoleProvisioner::assignRole($event->user, $event->team, $pivotRole);
            } catch (\Throwable $e) {
                Log::error('Failed to sync Jetstream team role into RBAC system', [
                    'team_id' => $event->team->id,
                    'user_id' => $event->user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        // Listen for failed queue jobs and notify monitoring
        Event::listen(JobFailed::class, function ($event) {
            try {
                $listener = new NotifyOnJobFailed;
                $listener->handle($event);
            } catch (\Throwable $e) {
                Log::error('Failed to notify on job failure', ['error' => $e->getMessage()]);
            }
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

        // Login rate limiting is defined in FortifyServiceProvider (which
        // boots after this provider, so its registration is the one that
        // actually takes effect) and applied via config('fortify.limiters.
        // login') to Fortify's own /login route -- a duplicate 'login'
        // limiter here was pure dead code, silently discarded on every
        // request, confirmed via RateLimiter's internal $limiters array.

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
    }
}
