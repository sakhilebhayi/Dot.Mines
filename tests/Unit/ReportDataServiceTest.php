<?php

namespace Tests\Unit;

use App\Models\ComplianceViolation;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\MachineMetric;
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

    public function test_compliance_report_reflects_real_violations_and_scores_them(): void
    {
        $team = Team::factory()->create();

        ComplianceViolation::create([
            'team_id' => $team->id,
            'violation_type' => 'safety_procedure',
            'description' => 'Operator missing PPE on site inspection',
            'severity' => 'critical',
            'detected_at' => now()->subDays(3),
            'remediation_deadline' => now()->subDay(), // overdue, unresolved
        ]);

        ComplianceViolation::create([
            'team_id' => $team->id,
            'violation_type' => 'environmental',
            'description' => 'Dust suppression system offline',
            'severity' => 'medium',
            'detected_at' => now()->subDays(2),
            'remediation_deadline' => now()->addDays(5),
            'resolved_at' => now()->subDay(),
        ]);

        $report = $this->reportFor($team, 'compliance');
        $data = app(ReportDataService::class)->build($report);

        $this->assertCount(2, $data['rows']);
        $this->assertSame(2, $data['summary']['Total Violations']);
        $this->assertSame(1, $data['summary']['Resolved']);
        $this->assertSame(1, $data['summary']['Overdue']);
        $this->assertSame(1, $data['summary']['Critical']);
        // 100 - 1 unresolved*5 - 1 overdue*10 - 1 critical*5 = 80
        $this->assertSame(80, $data['summary']['Compliance Score']);
    }

    public function test_compliance_report_is_scoped_to_the_reports_own_team(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        ComplianceViolation::create([
            'team_id' => $team->id,
            'violation_type' => 'safety_procedure',
            'description' => 'Own team violation',
            'severity' => 'low',
            'detected_at' => now()->subDay(),
        ]);
        ComplianceViolation::create([
            'team_id' => $otherTeam->id,
            'violation_type' => 'safety_procedure',
            'description' => 'Other team violation',
            'severity' => 'critical',
            'detected_at' => now()->subDay(),
        ]);

        $report = $this->reportFor($team, 'compliance');
        $data = app(ReportDataService::class)->build($report);

        $this->assertCount(1, $data['rows']);
        $this->assertSame(1, $data['summary']['Total Violations']);
    }

    public function test_unsupported_type_throws_instead_of_silently_returning_nothing(): void
    {
        $team = Team::factory()->create();
        $report = $this->reportFor($team, 'truck_sensors');

        $this->expectException(\InvalidArgumentException::class);

        app(ReportDataService::class)->build($report);
    }

    /**
     * operating_hours on machine_metrics is a cumulative engine-hours meter
     * (same semantics as MachinePerformanceService::dayDelta() and the Bell
     * production derivation). The utilization report used to SUM the meter
     * readings -- multiplying lifetime hours by the reading count and
     * producing utilization percentages in the thousands.
     */
    public function test_fleet_utilization_uses_the_counter_delta_not_a_sum_of_meter_readings(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT-01', 'status' => 'active']);

        // Two cumulative readings inside a 1-day window: 8 real hours worked.
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 100.0,
            'recorded_at' => now()->startOfDay()->addHours(6),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 108.0,
            'recorded_at' => now()->startOfDay()->addHours(16),
        ]);

        $report = $this->reportFor($team, 'fleet_utilization', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $data = app(ReportDataService::class)->build($report);

        $row = collect($data['rows'])->firstWhere(0, 'ADT-01');
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(8.0, $row[3], 0.01, 'Hours must be the counter delta (108 - 100), not the 208 the old sum produced.');
        $this->assertSame('33.3%', $row[5]);
        $this->assertEqualsWithDelta(8.0, $data['summary']['Total Operating Hours'], 0.01);
    }

    public function test_fleet_utilization_reports_insufficient_data_for_a_single_reading(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT-02']);

        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 5000.0,
            'recorded_at' => now()->startOfDay()->addHours(8),
        ]);

        $report = $this->reportFor($team, 'fleet_utilization', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $data = app(ReportDataService::class)->build($report);

        $row = collect($data['rows'])->firstWhere(0, 'ADT-02');
        $this->assertNotNull($row);
        $this->assertSame('Insufficient data', $row[3], 'One meter reading cannot yield a duration; do not fabricate one.');
        $this->assertSame('—', $row[5]);
    }
}
