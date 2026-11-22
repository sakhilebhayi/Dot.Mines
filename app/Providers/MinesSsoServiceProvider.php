<?php

namespace App\Providers;

use App\Http\Middleware\CaptureSsoNext;
use App\Sso\MinesSsoController;
use App\Support\ApiPayload;
use Dot\SsoClient\Http\Controllers\SsoController;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class MinesSsoServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // The package's controller, with Mines' two-factor rule on finishing a sign-in (routes and the claim pages resolve it from here).
        $this->app->bind(SsoController::class, MinesSsoController::class);
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('web', CaptureSsoNext::class);

        $this->enforceLaxSession();
    }

    /**
     * SameSite guard.
     * The code default is `strict`, which drops the session cookie on the cross-site return from InfoDot, so the callback
     * would find no pending state ("that sign-in link has already been used"). Production sets SESSION_SAME_SITE=lax;
     * with central sign-in on, `strict` is never allowed to stand.
     */
    public function enforceLaxSession(): void
    {
        if (config('dotsso.enabled') && strtolower(ApiPayload::str(config('session.same_site'))) === 'strict') {
            config(['session.same_site' => 'lax']);
        }
    }
}
