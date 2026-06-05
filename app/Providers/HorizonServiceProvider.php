<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::routeMailNotificationsTo((string) config('mail.from.address', ''));

        // Pause Horizon on deployment; resume after health check.
        Horizon::night();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Grants access to users with the 'admin' role or those listed in
     * HORIZON_ADMINS env var (comma-separated email list).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if ($user === null) {
                return false;
            }

            // Allow via explicit admin email list in .env
            $admins = array_filter(array_map(
                'trim',
                explode(',', (string) env('HORIZON_ADMINS', ''))
            ));

            if (in_array($user->email, $admins, true)) {
                return true;
            }

            // Allow if user has admin role in their current team
            return $user->hasTeamRole($user->currentTeam, 'admin');
        });
    }
}
