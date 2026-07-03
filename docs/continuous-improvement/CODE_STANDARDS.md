# Code Standards

> Naming conventions, architecture patterns, and documentation standards for the Mines platform.

---

## PHP / Laravel Conventions

### Naming
| Type | Convention | Example |
|---|---|---|
| Class | PascalCase | `MachineTelemetryService` |
| Method | camelCase | `forMachines()` |
| Variable | camelCase | `$machineIds` |
| Boolean variable | `is` / `has` / `can` prefix | `$isOffline`, `$hasData`, `$canEdit` |
| Model scope | `scope` prefix | `scopeActive()` → called as `->active()` |
| Configuration key | snake_case with dots | `integrations.bell_polling.location_interval_seconds` |
| Route name | dot.separated | `fleet.show`, `billing.index` |
| Event | Past-tense noun | `MachineLocationUpdated`, `MachineStatusChanged` |
| Job | `Job` suffix | `SyncBellLocationsJob` |
| Command | Verb-noun, colon-separated | `bell:watch-locations`, `bell:backfill-history` |
| Service | `Service` suffix | `MachineKpiService` |

### Architecture Layers
```
HTTP Request / Artisan Command
    ↓
Controller / Livewire Component   (no business logic, thin)
    ↓
Service Class                     (business logic, injectable)
    ↓
Repository / Eloquent Model       (data access)
    ↓
Database
```

**Rules**:
- Livewire components call services; they do not query models directly for complex logic.
- Services do not know about HTTP requests or Livewire.
- Models are repositories; complex queries go in model scopes or dedicated query classes.
- Events are dispatched from services, not from controllers or Livewire.

### Service Design
```php
// ✅ Good: Integration-agnostic service
class MachineKpiService
{
    public function getDailyKpiSummary(array $machineIds, string $startDate, string $endDate): array
    {
        // Bell source
        // machine_metrics source
        // return merged result
    }
}

// ❌ Bad: Bell-specific logic in Livewire component
class ProductionDashboard extends Component
{
    public function getBellKpiSummaryProperty(): array
    {
        BellEquipmentDailyKpi::whereIn(...)->get(); // direct OEM table in UI layer
    }
}
```

### Telemetry Snapshot Contract
Every telemetry snapshot array must include:
```php
[
    'status'                => string,   // 'working'|'travelling'|'parked'|'offline'|'maintenance'
    'status_label'          => string,
    'status_color'          => string,
    'engine_running'        => bool|null,
    'fuel_remaining_percent'=> float|null,
    'operating_hours'       => float|null,
    'idle_hours'            => float|null,
    'load_count'            => int|null,
    'odometer'              => float|null,
    'latitude'              => float|null,
    'longitude'             => float|null,
    'speed_kmh'             => float|null,
    'last_seen_at'          => string|null,  // ISO 8601
    'last_seen_human'       => string|null,  // "5 minutes ago"
    'equipment_key'         => int|null,     // Bell-specific; null for other OEMs
    'telemetry_source'      => string,       // 'bell'|'machine_metric'|'machine'|'none'
]
```

---

## Livewire Conventions

### Component Structure
```php
class MyComponent extends Component
{
    // 1. Public properties (Livewire state)
    public string $search = '';

    // 2. Protected/private state (not exposed to JS)

    // 3. mount()
    public function mount(): void { }

    // 4. Lifecycle hooks (hydrate, updating*, updated*)

    // 5. Computed properties (getCamelCaseProperty)

    // 6. Actions (public methods called by wire:click)

    // 7. render()
    public function render(): View
    {
        return view('livewire.my-component');
    }
}
```

### Do Not
- Query Bell (or any OEM) models directly in Livewire components — use services
- Write business logic in Livewire components — extract to services
- Call `Auth::user()` more than once in a method — assign to local variable

---

## Blade / Frontend Conventions

### Tailwind
- Use `dark:` variants for all colour classes
- Use responsive prefixes (`md:`, `lg:`) before `xl:`/`2xl:`
- Prefer `gap-*` over `margin` for flex/grid layouts
- Extract repeated utility combinations to Blade components

### Conditional rendering
```blade
{{-- ✅ Good: use @if with a named variable --}}
@if ($hasBellTelemetry)
    {{-- Bell stats --}}
@endif

{{-- ❌ Bad: complex PHP inline --}}
@if (count($machines) > 0 && $team->id === 4)
```

---

## API Conventions

- All API routes under `/api/v1/` prefix
- Always return JSON; never redirect on API routes
- Use Form Requests for validation
- Use API Resources for response shaping
- Log every API action affecting data at `INFO` level

---

## Documentation Standards

- Every public service method has a PHPDoc block
- PHPDoc includes `@param`, `@return`, and a one-line description
- Complex algorithms have inline comments explaining *why*, not *what*
- New `.env` variables have a comment explaining their purpose and valid values
- Every new Artisan command has a `$description` string and `--help` output
