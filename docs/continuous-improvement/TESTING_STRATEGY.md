# Testing Strategy

> Define the testing approach, coverage targets, and quality standards.

---

## Current Testing Score: 58/100

---

## Coverage Targets

| Layer | Current | Target | Notes |
|---|---|---|---|
| Feature tests (HTTP) | ~40% | 80% | Critical paths first |
| Unit tests (services) | ~20% | 70% | New services must ship with tests |
| Integration tests (DB + jobs) | ~30% | 60% | Bell sync + OEM adapters |
| Livewire component tests | ~15% | 50% | Auth, form submission, state |
| Load tests | 0% | Baseline established | `k6` scripts for API + sync pipeline |
| Security tests | 0% | Key paths covered | OWASP test cases |

---

## Test Types

### Unit Tests
**What**: Individual class/method logic without DB or HTTP.

**Target**: All service classes (`MachineTelemetryService`, `MachineKpiService`, `MachineFaultCodeService`, `BellIso15143Service`, etc.)

```php
// Example: MachineKpiService unit test
it('returns zero totals when no machine IDs provided', function () {
    $result = app(MachineKpiService::class)->getDailyKpiSummary([], '2026-07-01', '2026-07-01');
    expect($result['has_data'])->toBeFalse();
    expect($result['total_loads'])->toBe(0);
});
```

### Feature Tests (HTTP)
**What**: Full HTTP request → response cycle using `RefreshDatabase`.

**Priority order**:
1. Authentication routes (login, logout, MFA)
2. API machine CRUD (team isolation critical)
3. API alert CRUD
4. API fuel transactions
5. Production records
6. Integration manager (credential save, sync trigger)

### Livewire Component Tests
**What**: Use `Livewire::test()` to test component state, actions, and rendered output.

```php
// Example
it('shows Bell telemetry stats when machines have Bell equipment', function () {
    $user = createAdminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);
    BellEquipment::factory()->create(['machine_id' => $machine->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('hasBellTelemetry', true);
});
```

### Integration Tests
**What**: Full pipeline tests including DB writes and job execution.

**Priority**:
1. Bell ISO15143-3 sync (mock HTTP, verify DB state)
2. `SyncIntegrationJob` team isolation
3. Alert creation from caution codes
4. `BellWatchLocationsCommand` single cycle

### Load Tests (k6)
```javascript
// k6/fleet-api.js
import http from 'k6/http';
export const options = {
    vus: 100,
    duration: '60s',
    thresholds: { 'http_req_duration': ['p(95)<200'] },
};
export default function () {
    http.get('https://mines.infodot.co.za/api/v1/machines', {
        headers: { Authorization: `Bearer ${__ENV.API_TOKEN}` },
    });
}
```

### Security Tests
**What**: Verify OWASP Top 10 is mitigated for critical endpoints.

- SQL injection on all search/filter parameters
- Mass assignment on Machine, Alert, User models
- IDOR on machine, alert, fuel endpoints (team isolation)
- Rate limiting enforcement
- Unauthenticated access rejection

---

## Testing Standards

### Every New Feature Must Include
- At least one feature test covering the happy path
- At least one feature test covering the failure/validation path
- Tests for team data isolation (user cannot access another team's data)

### Every New Service Must Include
- Unit test for the primary method
- Unit test for empty/null input handling
- Integration test if it touches the database

### Test Naming Convention
```php
it('returns active fault codes for linked Bell equipment', fn () => ...);
it('returns empty collection when no machines are provided', fn () => ...);
it('resolves maintenance status from machine_metrics when Bell is absent', fn () => ...);
```

### Test Setup Pattern
```php
private function adminUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    return $user;
}
```

---

## CI Test Pipeline (Target)

```yaml
# .github/workflows/tests.yml
- run: php artisan test --no-coverage --parallel
- run: ./vendor/bin/phpstan analyse --no-progress
- run: ./vendor/bin/pint --test
- run: composer audit
- run: npm audit --audit-level=high
```
