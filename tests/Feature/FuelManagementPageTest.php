<?php

namespace Tests\Feature;

use App\Models\FuelTank;
use App\Models\Team;
use App\Models\User;
use App\Services\FuelManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /fuel page had no feature test coverage before this file, so nothing
 * verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/fuel-management.blade.php (and the shared daisyui
 * "mines" theme in tailwind.config.js it relies on for card/stat/badge/alert
 * styling) to confirm the page still compiles and renders.
 */
class FuelManagementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_fuel_management(): void
    {
        $response = $this->get('/fuel');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_fuel_management_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/fuel');

        $response->assertOk();
        $response->assertSee('Fuel Management');
    }

    /**
     * A logged theft/spillage transaction used to be indistinguishable from
     * ordinary consumption anywhere in the UI -- no alert, and the amount
     * was silently folded into "Total Consumed". This proves the loss is
     * now visible both as its own stat and as a real active alert.
     */
    public function test_a_logged_theft_shows_up_as_fuel_loss_and_a_real_alert_on_the_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $tank = FuelTank::create([
            'team_id' => $team->id,
            'name' => 'Tank 1',
            'capacity_liters' => 10000,
            'current_level_liters' => 5000,
            'minimum_level_liters' => 1000,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        app(FuelManagementService::class)->recordTransaction([
            'team_id' => $team->id,
            'fuel_tank_id' => $tank->id,
            'user_id' => $user->id,
            'transaction_type' => 'theft',
            'quantity_liters' => 150,
            'total_cost' => 3000,
            'fuel_type' => $tank->fuel_type,
            'transaction_date' => now(),
        ]);

        $response = $this->actingAs($user)->get('/fuel');

        $response->assertOk();
        $response->assertSee('Fuel Loss');
        $response->assertSee('150.0L theft', false);
        $response->assertSee('Fuel Theft Reported');
    }
}
