<?php

namespace Tests\Unit;

use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Report;
use App\Models\Team;
use App\Services\Reports\ReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the real report data queries that replace the
 * "generation" step that used to never run at all -- Report::create()
 * would leave every report at status='pending' forever, since nothing in
 * the app ever called Report::markCompleted().
 */
class ReportDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private function reportFor(Team $team, string $type, array $filters = []): Report
    {
        return Report::create([
            'team_id' => $team->id,
            'title' => 'Test Report',
            'type' => $type,
            'format' => 'csv',
            'status' => 'pending',
            'filters' => array_merge([
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
            ], $filters),
        ]);
    }

    public function test_production_report_reflects_real_production_records(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->subDays(2)->toDateString(),
            'shift' => 'day',
            'quantity_produced' => 500,
            'target_quantity' => 400,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $report = $this->reportFor($team, 'production');
        $data = app(ReportDataService::class)->build($report);

        $this->assertCount(1, $data['rows']);
        $this->assertSame(500.0, $data['summary']['Total Produced']);
        $this->assertSame($machine->name, $data['rows'][0][2]);
    }

    public function test_fuel_consumption_report_reflects_real_transactions(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        FuelTransaction::factory()->create([
            'team_id' => $team->id,
            'fuel_tank_id' => null,
            'machine_id' => $machine->id,
            'transaction_type' => 'dispensing',
            'transaction_date' => now()->subDays(1),
            'quantity_liters' => 250,
            'unit_price' => 30,
            'total_cost' => 7500,
        ]);

        $report = $this->reportFor($team, 'fuel_consumption');
        $data = app(ReportDataService::class)->build($report);

        $this->assertCount(1, $data['rows']);
        $this->assertSame(250.0, $data['summary']['Total Liters Dispensed']);
        $this->assertSame(7500.0, $data['summary']['Total Cost']);
    }

    public function test_data_is_scoped_to_the_reports_own_team(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $ownMachine = Machine::factory()->create(['team_id' => $team->id]);
        $otherMachine = Machine::factory()->create(['team_id' => $otherTeam->id]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $ownMachine->id,
            'record_date' => now()->subDays(1)->toDateString(),
            'shift' => 'day',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);
        ProductionRecord::create([
            'team_id' => $otherTeam->id,
            'machine_id' => $otherMachine->id,
            'record_date' => now()->subDays(1)->toDateString(),
            'shift' => 'day',
            'quantity_produced' => 9999,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $report = $this->reportFor($team, 'production');
        $data = app(ReportDataService::class)->build($report);

        $this->assertCount(1, $data['rows']);
        $this->assertSame(100.0, $data['summary']['Total Produced']);
    }

    public function test_unsupported_type_throws_instead_of_silently_returning_nothing(): void
    {
        $team = Team::factory()->create();
        $report = $this->reportFor($team, 'truck_sensors');

        $this->expectException(\InvalidArgumentException::class);

        app(ReportDataService::class)->build($report);
    }
}
