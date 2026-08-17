# Dot.Mines: Cushion — Fuel Reserve Runway Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show each team approximately how many days of fuel reserves they have at their current dispensing rate, on the existing dashboard — honestly, including when there's nothing to compute yet.

**Architecture:** A new `FuelReserveRunwayCalculator` service (pure computation, testable in isolation, relying on `HasTeamFilters`' existing global scope for team isolation) is called from a new Livewire component, `FuelCushion`, following the existing `Dashboard` component's team-null-guard pattern. Rendered on `resources/views/livewire/dashboard.blade.php`.

**Tech Stack:** Laravel 12 (existing app), Livewire 3 (existing), PHPUnit — no new dependencies.

## Global Constraints

- Current reserves = sum of `current_level_liters` across tanks where `status = 'active'` only (reuses `FuelTank::scopeActive()`).
- Daily consumption = average `dispensing`-type `quantity_liters` per day over the trailing 30 days (or fewer distinct days if less history exists, minimum 1) — all other `transaction_type` values excluded (per spec's Computation section).
- Zero active tanks or zero dispensing transactions → the whole section is omitted, never a fabricated 0/N-A figure.
- Zero-consumption-rate case never divides — shows a factual message, `days: null` (per spec's Computation section).
- The what-if is real (top-consuming machine identified from actual data) or omitted — never fabricated (per spec's Computation section).
- Team-scoping relies on the existing `HasTeamFilters` global scope — no manual `team_id` filtering in the new code.

---

### Task 1: `FuelReserveRunwayCalculator` service

**Files:**
- Create: `app/Services/FuelReserveRunwayCalculator.php`
- Test: `tests/Unit/FuelReserveRunwayCalculatorTest.php`

**Interfaces:**
- Consumes: `App\Models\FuelTank`, `App\Models\FuelTransaction`, `App\Models\Machine` (existing, team-scoped via `HasTeamFilters`).
- Produces: `FuelReserveRunwayCalculator::calculate(): array` (returns the contract shape from the spec) — consumed by Task 2's Livewire component. No team-id parameter — relies on the authenticated user's `current_team_id`, matching this repo's existing `HasTeamFilters` convention.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Services\FuelReserveRunwayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelReserveRunwayCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsTeamMember(): Team
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        $this->actingAs($user);

        return $team;
    }

    private function createTank(Team $team, string $status, float $currentLevel, float $capacity = 10000): FuelTank
    {
        return FuelTank::create([
            'team_id' => $team->id,
            'name' => 'Tank ' . uniqid(),
            'capacity_liters' => $capacity,
            'current_level_liters' => $currentLevel,
            'minimum_level_liters' => $capacity * 0.1,
            'fuel_type' => 'diesel',
            'status' => $status,
        ]);
    }

    private function createTransaction(Team $team, FuelTank $tank, string $type, float $quantity, \DateTimeInterface $date, ?Machine $machine = null): FuelTransaction
    {
        return FuelTransaction::create([
            'team_id' => $team->id,
            'fuel_tank_id' => $tank->id,
            'machine_id' => $machine?->id,
            'transaction_type' => $type,
            'quantity_liters' => $quantity,
            'unit_price' => 20,
            'total_cost' => $quantity * 20,
            'fuel_type' => 'diesel',
            'transaction_date' => $date,
        ]);
    }

    public function test_reserves_only_count_active_tanks(): void
    {
        $team = $this->actingAsTeamMember();
        $active = $this->createTank($team, 'active', 5000);
        $this->createTank($team, 'maintenance', 3000); // excluded

        $this->createTransaction($team, $active, 'dispensing', 100, now());

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertEqualsWithDelta(5000.0, $result['current_reserves_liters'], 0.01);
    }

    public function test_consumption_rate_excludes_non_dispensing_types(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);

        $this->createTransaction($team, $tank, 'dispensing', 100, now());
        $this->createTransaction($team, $tank, 'refill', 9999, now());
        $this->createTransaction($team, $tank, 'theft', 500, now());

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertEqualsWithDelta(100.0, $result['daily_consumption_liters'], 0.01);
    }

    public function test_insufficient_data_when_no_tanks(): void
    {
        $this->actingAsTeamMember();

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertFalse($result['available']);
        $this->assertSame('insufficient_data', $result['reason']);
    }

    public function test_insufficient_data_when_no_dispensing_transactions(): void
    {
        $team = $this->actingAsTeamMember();
        $this->createTank($team, 'active', 5000);
        // No transactions at all.

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertFalse($result['available']);
    }

    public function test_days_computed_correctly(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 3000);

        $this->createTransaction($team, $tank, 'dispensing', 100, now());

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertSame(30, $result['days']); // 3000 / 100
    }

    public function test_what_if_identifies_top_consuming_machine(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);
        $bigMachine = Machine::create(['team_id' => $team->id, 'name' => 'Excavator 1', 'status' => 'active', 'type' => 'excavator']);
        $smallMachine = Machine::create(['team_id' => $team->id, 'name' => 'Truck 1', 'status' => 'active', 'type' => 'truck']);

        $this->createTransaction($team, $tank, 'dispensing', 300, now(), $bigMachine);
        $this->createTransaction($team, $tank, 'dispensing', 50, now(), $smallMachine);

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertNotNull($result['what_if']);
        $this->assertSame('Excavator 1', $result['what_if']['machine_name']);
    }

    public function test_what_if_is_null_without_machine_attributed_dispensing(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);

        $this->createTransaction($team, $tank, 'dispensing', 100, now(), null);

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertNull($result['what_if']);
    }

    public function test_a_second_teams_data_never_affects_the_first_teams_figures(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);
        $this->createTransaction($team, $tank, 'dispensing', 100, now());

        $otherTeam = Team::factory()->create();
        $otherTank = FuelTank::create([
            'team_id' => $otherTeam->id, 'name' => 'Other Tank', 'capacity_liters' => 99999,
            'current_level_liters' => 99999, 'minimum_level_liters' => 100, 'fuel_type' => 'diesel', 'status' => 'active',
        ]);
        FuelTransaction::create([
            'team_id' => $otherTeam->id, 'fuel_tank_id' => $otherTank->id, 'transaction_type' => 'dispensing',
            'quantity_liters' => 9999, 'unit_price' => 20, 'total_cost' => 9999 * 20, 'fuel_type' => 'diesel', 'transaction_date' => now(),
        ]);

        $result = (new FuelReserveRunwayCalculator())->calculate();

        $this->assertEqualsWithDelta(5000.0, $result['current_reserves_liters'], 0.01);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=FuelReserveRunwayCalculatorTest`
Expected: FAIL — `FuelReserveRunwayCalculator` class does not exist yet.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Services;

use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;

class FuelReserveRunwayCalculator
{
    private const TRAILING_DAYS = 30;

    public function calculate(): array
    {
        $currentReserves = (float) FuelTank::active()->sum('current_level_liters');

        $windowStart = now()->subDays(self::TRAILING_DAYS);
        $dispensing = FuelTransaction::where('transaction_type', 'dispensing')
            ->where('transaction_date', '>=', $windowStart)
            ->get();

        if ($currentReserves <= 0 && FuelTank::active()->count() === 0) {
            return $this->unavailable();
        }

        if ($dispensing->isEmpty()) {
            return $this->unavailable();
        }

        $distinctDays = $dispensing->map(fn ($t) => $t->transaction_date->format('Y-m-d'))->unique()->count();
        $daysSpanned = min(max(1, $distinctDays), self::TRAILING_DAYS);

        $totalDispensed = (float) $dispensing->sum('quantity_liters');
        $dailyConsumption = $totalDispensed / $daysSpanned;

        $hasNoRecentConsumption = $dailyConsumption <= 0;
        $days = $hasNoRecentConsumption ? null : (int) round($currentReserves / $dailyConsumption);

        $basis = sprintf(
            'Active tank reserves divided by average daily dispensing volume over the trailing %d day(s) of data, as of %s.',
            $daysSpanned,
            now()->toDateString()
        );

        $whatIf = $this->computeWhatIf($dispensing, $dailyConsumption, $currentReserves, $daysSpanned);

        return [
            'available' => true,
            'reason' => null,
            'current_reserves_liters' => $currentReserves,
            'daily_consumption_liters' => $dailyConsumption,
            'days' => $days,
            'has_no_recent_consumption' => $hasNoRecentConsumption,
            'basis' => $basis,
            'what_if' => $whatIf,
        ];
    }

    private function unavailable(): array
    {
        return [
            'available' => false,
            'reason' => 'insufficient_data',
            'current_reserves_liters' => null,
            'daily_consumption_liters' => null,
            'days' => null,
            'has_no_recent_consumption' => null,
            'basis' => null,
            'what_if' => null,
        ];
    }

    private function computeWhatIf($dispensing, float $dailyConsumption, float $currentReserves, int $daysSpanned): ?array
    {
        $topMachine = $dispensing->whereNotNull('machine_id')
            ->groupBy('machine_id')
            ->map(fn ($group) => $group->sum('quantity_liters'))
            ->sortDesc();

        if ($topMachine->isEmpty()) {
            return null;
        }

        $topMachineId = $topMachine->keys()->first();
        $machine = Machine::find($topMachineId);
        if (! $machine) {
            return null;
        }

        $machineDailyAvg = $topMachine->first() / $daysSpanned;
        $adjustedRate = $dailyConsumption - $machineDailyAvg;

        if ($adjustedRate <= 0) {
            return null;
        }

        return [
            'machine_name' => $machine->name,
            'days_without_machine' => (int) round($currentReserves / $adjustedRate),
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FuelReserveRunwayCalculatorTest`
Expected: PASS (all 8 tests). If `Machine::create()` in the tests fails due to required fields this plan didn't anticipate, check `app/Models/Machine.php`'s `$fillable` and the migration for any other required columns, and add them to the test fixtures — do not weaken the test.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FuelReserveRunwayCalculator.php tests/Unit/FuelReserveRunwayCalculatorTest.php
git commit -m "feat(cushion): add FuelReserveRunwayCalculator for the fuel_reserve_runway cushion dimension"
```

---

### Task 2: `FuelCushion` Livewire component + dashboard wiring

**Files:**
- Create: `app/Livewire/FuelCushion.php`
- Create: `resources/views/livewire/fuel-cushion.blade.php`
- Modify: `resources/views/livewire/dashboard.blade.php`

**Interfaces:**
- Consumes: `FuelReserveRunwayCalculator::calculate()` (Task 1).
- Produces: `<livewire:fuel-cushion />`.

- [ ] **Step 1: Write the Livewire component**

```php
<?php

namespace App\Livewire;

use App\Models\Team;
use App\Services\FuelReserveRunwayCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FuelCushion extends Component
{
    public array $cushion = [];

    public function mount(): void
    {
        if (! $this->resolveCurrentTeam()) {
            return;
        }

        $this->cushion = (new FuelReserveRunwayCalculator())->calculate();
    }

    private function resolveCurrentTeam(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    public function render()
    {
        return view('livewire.fuel-cushion');
    }
}
```

- [ ] **Step 2: Write the Blade view**

```blade
@if(($cushion['available'] ?? false))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-lg">⛽</span>
            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wide">Fuel Cushion</h3>
        </div>

        @if($cushion['has_no_recent_consumption'])
            <div class="text-xl font-bold text-gray-700 dark:text-gray-200 mb-1">
                No recent fuel consumption recorded.
            </div>
        @else
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mb-1">
                Approximately {{ $cushion['days'] }} day{{ $cushion['days'] === 1 ? '' : 's' }} of reserves at current usage.
            </div>
        @endif

        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $cushion['basis'] }}</p>

        @if($cushion['what_if'])
            <p class="text-xs text-gray-600 dark:text-gray-300 mt-2">
                If <strong>{{ $cushion['what_if']['machine_name'] }}</strong>'s consumption were removed, this would extend to approximately {{ $cushion['what_if']['days_without_machine'] }} day{{ $cushion['what_if']['days_without_machine'] === 1 ? '' : 's' }}.
            </p>
        @endif
    </div>
@endif
```

- [ ] **Step 3: Wire into the dashboard**

In `resources/views/livewire/dashboard.blade.php`, add `<livewire:fuel-cushion />` immediately after the "Statistics Cards" grid `</div>` closing tag (before whatever section follows it — locate the exact closing tag by reading the file, since the grid's closing `</div>` isn't uniquely greppable from this plan alone).

- [ ] **Step 4: Run the full test suite**

Run: `php artisan test`
Expected: PASS, 0 new failures.

- [ ] **Step 5: Manual verification**

1. Start the dev server (this repo's usual local server command).
2. Log in as a real team member with at least one active `FuelTank` and some `dispensing`-type `FuelTransaction` rows.
3. Load the dashboard. Confirm the Fuel Cushion card appears with a real "approximately N days" figure and the basis line.
4. Confirm a `maintenance`/`decommissioned`-status tank's level is excluded from the reserves figure.
5. Add enough dispensing volume attributed to one machine to make it dominate consumption; reload; confirm a what-if line appears.
6. Confirm a brand-new team with zero tanks/transactions shows no Fuel Cushion card at all.
7. Stop the dev server.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/FuelCushion.php resources/views/livewire/fuel-cushion.blade.php resources/views/livewire/dashboard.blade.php
git commit -m "feat(cushion): show the fuel-reserve-runway cushion on the dashboard"
```
