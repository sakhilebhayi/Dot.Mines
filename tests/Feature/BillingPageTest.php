<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /billing page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered through the real HTTP
 * route (tests/Feature/BillingAuthorizationTest.php exercises the Livewire
 * component in isolation). Added while re-theming
 * resources/views/livewire/billing-portal.blade.php.
 */
class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_billing(): void
    {
        $response = $this->get('/billing');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_billing_with_no_subscription(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertOk();
        $response->assertSee('Your Machine Capacity');
    }
}
