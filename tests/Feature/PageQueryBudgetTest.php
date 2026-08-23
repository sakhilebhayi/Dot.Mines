<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Models\MachineMetric;
use App\Models\MaintenanceRecord;
use App\Models\MineArea;
use App\Models\Notification;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R8 performance ratchet: per-page query budgets, measured against a
 * realistically sized team (12 machines with metrics, health, maintenance,
 * production, alerts; 3 tanks; 40 fuel transactions; 5 geofences).
 *
 * Budgets are the measured 2026-08-23 counts plus small slack -- LESS than
 * one query per machine, so reintroducing any per-machine loop (an N+1 at
 * fleet size) fails this test before it ships. Measured history:
 * /fuel 93 -> 36 (per-machine dispensing stats collapsed into one grouped
 * query + latestFuelMetric eager relation), /maintenance 82 -> 37 (predictor
 * risk helpers batched into grouped metric windows; overdue lookups
 * prefetched; blade Machine::find() per AI recommendation removed),
 * /geofences 24 -> 15 (per-geofence entry counts grouped).
 *
 * If you add a feature that legitimately needs more queries, raise the
 * budget IN THE SAME PR with a sentence saying what the new queries are.
 * Never raise it to absorb an unexplained regression.
 */
class PageQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function seedRealisticTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $areas = MineArea::factory()->count(3)->create(['team_id' => $team->id]);

        $machines = Machine::factory()->count(12)->create([
            'team_id' => $team->id,
            'status' => 'active',
            'mine_area_id' => $areas[0]->id,
        ]);

        foreach ($machines as $i => $machine) {
            MachineMetric::factory()->count(8)->create([
                'team_id' => $team->id,
                'machine_id' => $machine->id,
            ]);
            MachineHealthStatus::factory()->create([
                'team_id' => $team->id,
                'machine_id' => $machine->id,
            ]);
            MaintenanceRecord::factory()->count(3)->create([
                'team_id' => $team->id,
                'machine_id' => $machine->id,
            ]);
            foreach (range(1, 4) as $d) {
                ProductionRecord::create([
                    'team_id' => $team->id,
                    'machine_id' => $machine->id,
                    'mine_area_id' => $areas[$i % 3]->id,
                    'record_date' => now()->subDays($d),
                    'shift' => 'continuous',
                    'quantity_produced' => 100 + $d,
                    'unit' => 'tonnes',
                    'status' => 'completed',
                ]);
            }
            Alert::factory()->count(2)->create([
                'team_id' => $team->id,
                'machine_id' => $machine->id,
                'status' => 'active',
            ]);
        }

        $tanks = FuelTank::factory()->count(3)->create(['team_id' => $team->id]);
        FuelTransaction::factory()->count(40)->create([
            'team_id' => $team->id,
            'fuel_tank_id' => $tanks[0]->id,
            'machine_id' => $machines[0]->id,
            'transaction_type' => 'dispensing',
        ]);
        Geofence::factory()->count(5)->create(['team_id' => $team->id]);
        Notification::factory()->count(10)->create(['team_id' => $team->id]);

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function pageBudgets(): array
    {
        return [
            'dashboard' => ['/dashboard', 32],
            'fleet' => ['/fleet', 42],
            'live map' => ['/map', 22],
            'fleet replay' => ['/fleet/replay', 15],
            'production' => ['/production', 36],
            'fuel' => ['/fuel', 44],
            'maintenance' => ['/maintenance', 45],
            'alerts' => ['/alerts', 16],
            'geofences' => ['/geofences', 21],
            'mine areas' => ['/mine-areas', 16],
            'reports' => ['/reports', 17],
        ];
    }

    #[DataProvider('pageBudgets')]
    public function test_page_stays_within_its_query_budget(string $page, int $budget): void
    {
        $user = $this->seedRealisticTeam();

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($user)->get($page);
        $response->assertOk();

        $count = count($queries);
        $shapes = array_count_values($queries);
        arsort($shapes);
        $worst = array_slice($shapes, 0, 3, true);
        $detail = '';
        foreach ($worst as $sql => $n) {
            if ($n >= 4) {
                $detail .= "\n  x{$n} ".substr($sql, 0, 140);
            }
        }

        $this->assertLessThanOrEqual(
            $budget,
            $count,
            "{$page} ran {$count} queries (budget {$budget}) against a 12-machine team."
            .($detail !== '' ? " Most-repeated shapes (a count near the machine count means an N+1):{$detail}" : '')
        );
    }
}
