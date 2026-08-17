# Dot.Mines: Cushion — Fuel Reserve Runway — Design

## Context

Third pilot implementation of the shared cross-ecosystem "cushion" contract
defined in Dot.Brain's
[brain.cushion.md](https://github.com/sakhilebhayi/Dot.Brain/blob/main/brain.cushion.md).
Dot.Mines has no cash-reserve or invoice data — but it has real
`FuelTank`/`FuelTransaction` data supporting a genuinely different, mining-
specific resilience dimension: **fuel reserve runway**, the physical-
resource analog of Dot.Finance's cash runway.

Real fields: `FuelTank.current_level_liters`/`capacity_liters`/
`minimum_level_liters`/`status` (with an existing `scopeActive` for
`status = 'active'`); `FuelTransaction.transaction_type` (real enum:
`refill`/`dispensing`/`delivery`/`transfer`/`adjustment`/`theft`/
`spillage`), `quantity_liters`, `transaction_date`, `machine_id`.

## Goal

Show each team approximately how many days of fuel reserves they have at
their current consumption rate — honestly, including when there's nothing
to compute from yet.

## New Registered Dimension: `fuel_reserve_runway`

Not previously in `brain.cushion.md`'s registry. Per that document's own
stated convention ("New dimensions get added to this table by whichever
platform builds the first real implementation of them"), this spec adds
`fuel_reserve_runway` (unit: days) to the shared registry alongside this
implementation, in the same commit series — not as a standalone doc edit.

## Computation (`App\Services\FuelReserveRunwayCalculator`)

- **Current reserves**: sum of `current_level_liters` across tanks where
  `status = 'active'` (reuses the existing `FuelTank::scopeActive()`).
- **Daily consumption rate**: average `quantity_liters` per day for
  `transaction_type = 'dispensing'` transactions only, over the trailing
  30 days (or however many distinct days of dispensing data exist, if
  fewer — minimum 1 day required). `refill`/`delivery`/`transfer`/
  `adjustment`/`theft`/`spillage` are excluded — only actual dispensing to
  machines represents real consumption; the others are replenishment,
  internal movement, or anomalies that would corrupt a burn-rate figure if
  included.
- **Insufficient data**: zero active tanks, or zero dispensing transactions
  in the window → the whole section is omitted, never shown as 0 or "N/A."
- **No-consumption case**: dispensing rate is 0 (data exists outside the
  window, or all recorded activity is non-dispensing) → no days figure is
  computed; a factual message instead ("No recent fuel consumption
  recorded").
- **Runway**: `days = current_reserves_liters / daily_consumption_rate`,
  displayed as **"approximately N days"** (whole number — tank gauge
  readings aren't precise enough to justify decimals).
- **What-if** (only when real data supports it): identify the single
  `machine_id` with the highest total dispensed volume over the window.
  Compute what the runway would be if that machine's consumption were
  removed from the daily rate. Omitted if there's no machine-attributed
  dispensing data, or if removing the top machine wouldn't change a
  computable rate into a meaningfully different one.

## Return Contract

```php
[
    'available' => bool,
    'reason' => ?string,               // 'insufficient_data' when available=false
    'current_reserves_liters' => ?float,
    'daily_consumption_liters' => ?float,
    'days' => ?int,                     // null when daily_consumption_liters <= 0 or unavailable
    'has_no_recent_consumption' => ?bool,
    'basis' => ?string,
    'what_if' => ?array,                // ['machine_name' => string, 'days_without_machine' => int] or null
]
```

## UI

A new Livewire component, `App\Livewire\FuelCushion`, following the
existing `Dashboard` component's team-null-guard pattern
(`resolveCurrentTeam()`), rendered on `resources/views/livewire/dashboard.blade.php`
alongside the existing Statistics Cards section. Card markup matches this
repo's existing Tailwind convention (`bg-white dark:bg-gray-800 rounded-lg
shadow-lg p-6 border border-gray-200 dark:border-gray-700`), not the
inline-style convention used in Dot.Finance/Dot.Billing — each platform's
own established styling is followed, not a forced shared visual system.

## Testing Plan

- `FuelReserveRunwayCalculatorTest`: reserves sum only counts active tanks
  (fixture with an active and an inactive/maintenance-status tank),
  consumption rate excludes non-dispensing transaction types, insufficient-
  data case (zero tanks or zero dispensing) returns `available: false`,
  no-consumption case (dispensing rate 0) returns `days: null` with
  `has_no_recent_consumption: true`, what-if identifies the correct top-
  consuming machine and computes the correct adjusted days figure, what-if
  is `null` when no machine-attributed dispensing exists, team-scoping via
  the existing `HasTeamFilters` global scope is respected (a second team's
  tanks/transactions never affect the first team's figures).
- No separate Livewire component test file required beyond a basic render
  smoke test, matching the granularity already used for this repo's other
  dashboard widgets — verified during implementation, not assumed.

## Explicitly Out of Scope

- Any other cushion dimension for Dot.Mines (equipment-fleet operational
  capacity margin, OEM-supplier concentration) — real candidates per this
  platform's own data model, but each is its own separate future addition,
  not bundled into this slice.
- DKP publication of this metric (per `brain.cushion.md`'s explicit scope
  boundary).
- Per-tank (rather than team-aggregate) runway breakdowns — a team-wide
  figure is what the shared UI pattern's "headline metric" calls for; a
  tank-by-tank drill-down is a natural but separate future addition.
