<?php

namespace Tests\Feature;

use App\Livewire\BillingPortal;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test: BillingPortal::subscribe(), manageBilling(),
 * cancelSubscription(), and resumeSubscription() never checked whether the
 * acting user actually owns the team, so any team member -- any role --
 * could cancel/resume the team's paid Stripe subscription or open the
 * Stripe billing portal. Fixed by gating each action on the existing
 * TeamPolicy::update (ownsTeam) check, same precedent as
 * app/Livewire/Settings.php. This proves a non-owner is blocked.
 */
class BillingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_team_member_cannot_cancel_the_team_subscription(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);

        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);

        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'price' => 999,
            'features' => [],
        ]);
        $subscription = Subscription::create([
            'team_id' => $team->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        Livewire::actingAs($member)
            ->test(BillingPortal::class)
            ->call('cancelSubscription');

        $this->assertSame('active', $subscription->fresh()->status);
    }
}
