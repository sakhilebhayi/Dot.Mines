<?php

namespace Tests\Feature;

use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\DataQuality\DataQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataQualityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_data_produces_no_findings(): void
    {
        $team = Team::factory()->create();

        ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => now()->subDay(),
            'shift' => 'day',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue(collect($results)->every(fn ($issues) => $issues->isEmpty()));
    }

    public function test_negative_production_quantity_is_flagged(): void
    {
        $team = Team::factory()->create();

        ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => now(),
            'shift' => 'day',
            'quantity_produced' => -50,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertCount(1, $results['production_records']);
        $this->assertSame('impossible_value', $results['production_records']->first()['category']);
    }

    public function test_future_dated_production_record_is_flagged(): void
    {
        $team = Team::factory()->create();

        ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => now()->addWeek(),
            'shift' => 'day',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['production_records']->contains('category', 'invalid_timestamp'));
    }

    public function test_duplicate_production_records_for_the_same_machine_shift_and_date_are_flagged(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => '2026-08-01',
            'shift' => 'day',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);
        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => '2026-08-01',
            'shift' => 'day',
            'quantity_produced' => 90,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['production_records']->contains('category', 'duplicate'));
    }

    public function test_fuel_transaction_with_mismatched_total_cost_is_flagged(): void
    {
        $team = Team::factory()->create();

        FuelTransaction::factory()->create([
            'team_id' => $team->id,
            'fuel_tank_id' => null,
            'quantity_liters' => 100,
            'unit_price' => 20,
            'total_cost' => 5000, // should be 2000
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['fuel_transactions']->contains('category', 'unit_inconsistency'));
    }

    public function test_non_positive_fuel_quantity_is_flagged(): void
    {
        $team = Team::factory()->create();

        FuelTransaction::factory()->create([
            'team_id' => $team->id,
            'fuel_tank_id' => null,
            'quantity_liters' => 0,
            'unit_price' => 20,
            'total_cost' => 0,
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['fuel_transactions']->contains('category', 'impossible_value'));
    }

    public function test_active_machine_with_stale_location_is_flagged(): void
    {
        $team = Team::factory()->create();

        Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'last_location_update' => now()->subDays(3),
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['machines']->contains('category', 'stale_telemetry'));
    }

    public function test_active_machine_with_recent_location_is_not_flagged(): void
    {
        $team = Team::factory()->create();

        Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'last_location_update' => now()->subMinutes(5),
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['machines']->isEmpty());
    }

    public function test_inactive_machine_with_stale_location_is_not_flagged(): void
    {
        $team = Team::factory()->create();

        Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'maintenance',
            'last_location_update' => now()->subDays(10),
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['machines']->isEmpty());
    }

    public function test_a_teams_data_never_leaks_into_another_teams_check(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        ProductionRecord::create([
            'team_id' => $otherTeam->id,
            'record_date' => now(),
            'shift' => 'day',
            'quantity_produced' => -50,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $results = app(DataQualityService::class)->checkTeam($team);

        $this->assertTrue($results['production_records']->isEmpty());
    }
}
