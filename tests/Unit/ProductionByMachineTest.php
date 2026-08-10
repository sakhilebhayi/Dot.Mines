<?php

namespace Tests\Unit;

use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionByMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_is_broken_down_and_ranked_per_machine(): void
    {
        $team = Team::factory()->create();
        $truckA = Machine::factory()->create(['team_id' => $team->id, 'name' => 'Truck A']);
        $truckB = Machine::factory()->create(['team_id' => $team->id, 'name' => 'Truck B']);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $truckA->id,
            'record_date' => now()->subDays(2),
            'shift' => 'day',
            'quantity_produced' => 300,
            'target_quantity' => 250,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);
        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $truckB->id,
            'record_date' => now()->subDay(),
            'shift' => 'day',
            'quantity_produced' => 100,
            'target_quantity' => 200,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $byMachine = app(ProductionService::class)->getProductionByMachine($team->id);

        $this->assertCount(2, $byMachine);

        // Ranked by total_produced descending, so Truck A (300) comes first.
        $this->assertSame('Truck A', $byMachine->first()['machine_name']);
        $this->assertSame(300.0, (float) $byMachine->first()['total_produced']);
        $this->assertEqualsWithDelta(120.0, $byMachine->first()['achievement_rate'], 0.1);

        $this->assertSame('Truck B', $byMachine->last()['machine_name']);
        $this->assertEqualsWithDelta(50.0, $byMachine->last()['achievement_rate'], 0.1);
    }

    public function test_records_older_than_30_days_are_excluded(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->subDays(45),
            'shift' => 'day',
            'quantity_produced' => 999,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $byMachine = app(ProductionService::class)->getProductionByMachine($team->id);

        $this->assertTrue($byMachine->isEmpty());
    }
}
