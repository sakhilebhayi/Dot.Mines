<?php

namespace App\Providers;

use App\Models\AIRecommendation;
use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Notification;
use App\Models\Report;
use App\Models\WebhookEndpoint;
use App\Policies\AIRecommendationPolicy;
use App\Policies\AlertPolicy;
use App\Policies\GeofencePolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\MachinePolicy;
use App\Policies\MineAreaPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ReportPolicy;
use App\Policies\WebhookEndpointPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Machine::class => MachinePolicy::class,
        Geofence::class => GeofencePolicy::class,
        Alert::class => AlertPolicy::class,
        Integration::class => IntegrationPolicy::class,
        MineArea::class => MineAreaPolicy::class,
        Report::class => ReportPolicy::class,
        Notification::class => NotificationPolicy::class,
        AIRecommendation::class => AIRecommendationPolicy::class,
        WebhookEndpoint::class => WebhookEndpointPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
