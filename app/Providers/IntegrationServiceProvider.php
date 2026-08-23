<?php

namespace App\Providers;

use App\Services\Integration\IntegrationService;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(IntegrationService::class, fn (): IntegrationService => new IntegrationService);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register any manufacturer services that are available
        // These will be loaded on demand by the IntegrationService
    }
}
