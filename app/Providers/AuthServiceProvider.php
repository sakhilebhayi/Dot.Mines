<?php

namespace App\Providers;

use App\Models\AIRecommendation;
use App\Models\Alert;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\Geofence;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ShiftTemplate;
use App\Policies\AIRecommendationPolicy;
use App\Policies\AlertPolicy;
use App\Policies\FeedCommentPolicy;
use App\Policies\FeedPostPolicy;
use App\Policies\GeofencePolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\MachinePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ReportPolicy;
use App\Policies\ShiftTemplatePolicy;
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
        Report::class => ReportPolicy::class,
        Notification::class => NotificationPolicy::class,
        AIRecommendation::class => AIRecommendationPolicy::class,
        FeedPost::class => FeedPostPolicy::class,
        FeedComment::class => FeedCommentPolicy::class,
        ShiftTemplate::class => ShiftTemplatePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
