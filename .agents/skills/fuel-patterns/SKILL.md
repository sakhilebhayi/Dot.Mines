---
name: fuel-patterns
description: >
  Mines platform fuel management patterns. Use when: recording fuel transactions, testing fuel
  API endpoints, debugging tank level calculations, working with FuelBudget allocations,
  understanding FuelAlert threshold logic, implementing fuel export, or building fuel-related
  Livewire components.
argument-hint: 'Describe the fuel management task you need help with'
---

# Fuel Management Patterns

## When to Use

- Recording or testing fuel transactions
- Debugging tank level sync issues
- Writing API tests for fuel endpoints
- Working with fuel budgets and monthly allocations
- Implementing fuel alert thresholds

---

## Transaction Recording Flow

```
POST /api/v1/fuel/transactions
          ↓
FuelTransaction::created
          ↓
FuelTransactionObserver::created()
  → updates fuel_tanks.current_level
  → calls FuelManagementService::checkAlertThresholds($tank)
  → if threshold crossed → FuelAlert fires → notification dispatched
```

---

## Pattern — Recording a Fuel Transaction

```php
// Via API
POST /api/v1/fuel/transactions
{
    "fuel_tank_id": 1,
    "machine_id": 5,
    "quantity_litres": 250.5,
    "transaction_type": "dispense",   // dispense|refuel|correction
    "operator_id": 3,
    "odometer_reading": 45230,
    "notes": "Routine fill-up"
}
```

---

## Pattern — Fuel Transaction Test

```php
#[Test]
public function fuel_transaction_updates_tank_level(): void
{
    $user = $this->adminUser();
    $tank = FuelTank::factory()->create([
        'team_id'       => $user->current_team_id,
        'current_level' => 1000,
        'capacity'      => 5000,
    ]);
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/fuel/transactions', [
            'fuel_tank_id'     => $tank->id,
            'machine_id'       => $machine->id,
            'quantity_litres'  => 250,
            'transaction_type' => 'dispense',
        ])
        ->assertCreated();

    // Tank level should decrease by 250
    $this->assertSame(750.0, (float) $tank->fresh()->current_level);
}
```

---

## Tank Level Integrity Check

```bash
# Verify each tank's current_level matches sum of transactions
php artisan tinker --execute '
App\Models\FuelTank::withoutGlobalScopes()->get()->each(function($tank) {
    $sum = App\Models\FuelTransaction::where("fuel_tank_id", $tank->id)->sum("quantity_litres");
    $drift = abs($tank->current_level - $sum);
    if ($drift > 0.01) echo "DRIFT: {$tank->name} — stored={$tank->current_level}, calc={$sum}\n";
});
'
```

---

## Commands Reference

```bash
# Run fuel tests
php artisan test --compact tests/Feature/FuelManagementTest.php

# Check FuelAlert thresholds
php artisan tinker --execute '
App\Models\FuelAlert::withoutGlobalScopes()->where("is_active",true)->get(["name","threshold_percentage"]);
'

# Monthly budget variance
php artisan tinker --execute '
$service = app(App\Services\FuelManagementService::class);
$variance = $service->calculateBudgetVariance(App\Models\Team::first(), now()->format("Y-m"));
var_dump($variance);
'
```
