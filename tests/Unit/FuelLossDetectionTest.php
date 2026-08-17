<?php

namespace Tests\Unit;

use App\Models\FuelAlert;
use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Team;
use App\Models\User;
use App\Services\FuelManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * theft/spillage were real, loggable transaction_type values, but logging
 * one just silently decremented the tank level -- exactly like a normal
 * dispensing entry. Nobody was ever notified a real loss had actually been
 * recorded, and team analytics folded theft/spillage into "total fuel
 * consumed" with no separate visibility into how much was lost.
 */
class FuelLossDetectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTank(Team $team, float $level = 5000): FuelTank
    {
        return FuelTank::create([
            'team_id' => $team->id,
            'name' => 'Test Tank',
            'capacity_liters' => 10000,
            'current_level_liters' => $level,
            'minimum_level_liters' => 1000,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);
    }

    private function recordLoss(Team $team, FuelTank $tank, string $type, float $liters, ?float $cost = null): FuelTransaction
    {
        return app(FuelManagementService::class)->recordTransaction([
            'team_id' => $team->id,
            'fuel_tank_id' => $tank->id,
            'user_id' => User::factory()->create()->id,
            'transaction_type' => $type,
            'quantity_liters' => $liters,
            'total_cost' => $cost,
            'fuel_type' => $tank->fuel_type,
            'transaction_date' => now(),
        ]);
    }

    public function test_recording_theft_creates_a_critical_fuel_loss_alert(): void
    {
        $team = Team::factory()->create();
        $tank = $this->makeTank($team);

        $transaction = $this->recordLoss($team, $tank, 'theft', 250, 5000);

        $alert = FuelAlert::where('team_id', $team->id)->where('alert_type', 'fuel_loss_reported')->first();
        $this->assertNotNull($alert, 'Logging a theft transaction should create a real alert, not just decrement the tank silently.');
        $this->assertSame('critical', $alert->severity);
        $this->assertStringContainsString('Theft', $alert->title);
        $this->assertStringContainsString('250', $alert->message);
        $this->assertSame($transaction->id, $alert->metadata['fuel_transaction_id']);
        $this->assertSame('theft', $alert->metadata['transaction_type']);
    }

    public function test_recording_spillage_creates_a_warning_fuel_loss_alert(): void
    {
        $team = Team::factory()->create();
        $tank = $this->makeTank($team);

        $this->recordLoss($team, $tank, 'spillage', 40);

        $alert = FuelAlert::where('team_id', $team->id)->where('alert_type', 'fuel_loss_reported')->first();
        $this->assertNotNull($alert);
        $this->assertSame('warning', $alert->severity);
        $this->assertStringContainsString('Spillage', $alert->title);
    }

    public function test_a_normal_dispensing_transaction_never_creates_a_loss_alert(): void
    {
        $team = Team::factory()->create();
        $tank = $this->makeTank($team);

        $this->recordLoss($team, $tank, 'dispensing', 100);

        $this->assertSame(0, FuelAlert::where('team_id', $team->id)->where('alert_type', 'fuel_loss_reported')->count());
    }

    /**
     * createFuelAlert()'s normal dedup rule (one alert per type per team
     * per 24h) is deliberately NOT applied to loss alerts -- a second real
     * theft report the same day, at a different tank, must never be
     * silently swallowed the way a second "tank low" warning would be.
     */
    public function test_two_theft_reports_the_same_day_at_different_tanks_both_alert(): void
    {
        $team = Team::factory()->create();
        $tankA = $this->makeTank($team);
        $tankB = $this->makeTank($team);

        $this->recordLoss($team, $tankA, 'theft', 50);
        $this->recordLoss($team, $tankB, 'theft', 75);

        $this->assertSame(2, FuelAlert::where('team_id', $team->id)->where('alert_type', 'fuel_loss_reported')->count());
    }

    public function test_team_analytics_breaks_out_theft_and_spillage_from_normal_consumption(): void
    {
        $team = Team::factory()->create();
        $tank = $this->makeTank($team);

        $this->recordLoss($team, $tank, 'dispensing', 500, 5000);
        $this->recordLoss($team, $tank, 'theft', 100, 2000);
        $this->recordLoss($team, $tank, 'spillage', 20, null);

        $analytics = app(FuelManagementService::class)->getTeamAnalytics(
            $team->id,
            Carbon::now()->subDay(),
            Carbon::now()->addDay()
        );

        // fuel_dispensed is legitimate use only -- unlike fuel_consumed
        // (unchanged, still includes loss for backward compatibility).
        $this->assertSame(500.0, $analytics['totals']['fuel_dispensed']);
        $this->assertSame(620.0, $analytics['totals']['fuel_consumed']); // 500 + 100 + 20

        $this->assertSame(100.0, $analytics['losses']['theft']['liters']);
        $this->assertSame(2000.0, $analytics['losses']['theft']['cost']);
        $this->assertSame(20.0, $analytics['losses']['spillage']['liters']);
        $this->assertSame(0.0, $analytics['losses']['spillage']['cost']); // no cost recorded for this one
        $this->assertSame(120.0, $analytics['losses']['total_liters']);
        $this->assertSame(2000.0, $analytics['losses']['total_cost']);
        // 120 lost out of 620 total removed from the tank.
        $this->assertEqualsWithDelta(19.4, $analytics['losses']['percent_of_total'], 0.1);
    }

    public function test_team_analytics_reports_zero_losses_cleanly_when_none_occurred(): void
    {
        $team = Team::factory()->create();
        $tank = $this->makeTank($team);
        $this->recordLoss($team, $tank, 'dispensing', 200);

        $analytics = app(FuelManagementService::class)->getTeamAnalytics(
            $team->id,
            Carbon::now()->subDay(),
            Carbon::now()->addDay()
        );

        $this->assertSame(0.0, $analytics['losses']['total_liters']);
        $this->assertSame(0.0, $analytics['losses']['percent_of_total']);
    }
}
