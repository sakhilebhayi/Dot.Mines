---
name: test-coverage-patterns
description: >
  Mines platform testing patterns and conventions. Use when: writing PHPUnit tests for Laravel
  controllers, testing API authentication, testing cross-team data isolation, testing role-based
  access, testing rate limits, testing validation rules, testing event listeners, writing Livewire
  component tests, or setting up test users with RBAC using TeamRoleService.
argument-hint: 'Describe the feature you need to test'
---

# Test Coverage Patterns — Mines Platform

## When to Use

- Writing new PHPUnit feature tests for a Laravel controller or service
- Setting up a test user with the correct team and RBAC roles
- Testing that unauthenticated requests are rejected (401)
- Testing that cross-team records return 404
- Testing that lower-permission roles are blocked (403)
- Testing validation errors (422)
- Testing rate limit enforcement (429)
- Testing queued jobs and events

---

## Conventions

- All feature tests extend `Tests\TestCase` and use `RefreshDatabase`
- API tests use `actingAs($user, 'sanctum')` — NOT `actingAs($user)` alone
- Use `#[Test]` attribute (PHPUnit 11) — NOT `/** @test */` docblock
- Create test files: `php artisan make:test --phpunit Feature/FeatureTest --no-interaction`
- After any PHP edit: `vendor/bin/pint --dirty --format agent`
- Run the file immediately: `php artisan test --compact tests/Feature/FeatureTest.php`

---

## Pattern 1 — Admin User with Full RBAC

Use for all tests that need a user who can perform any action.

```php
private function adminUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    return $user;
}
```

Required imports:
```php
use App\Models\User;
use App\Services\TeamRoleService;
```

---

## Pattern 2 — Viewer User (read-only, no create/update/delete)

Use for 403 tests where a lower-permission user tries a mutating action.

```php
private function viewerUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    $user->roles()->detach();
    $viewerRole = Role::where('team_id', $user->current_team_id)
                      ->where('name', 'viewer')
                      ->firstOrFail();
    $user->roles()->attach($viewerRole);
    return $user->fresh() ?? $user;
}
```

Required imports:
```php
use App\Models\Role;
```

**What viewer CAN do:** view_machines, view_geofences, view_reports, view_alerts, view_dashboard  
**What viewer CANNOT do:** create/update/delete anything

---

## Pattern 3 — 401 Authentication Tests

```php
#[Test]
public function unauthenticated_requests_are_rejected(): void
{
    $this->getJson('/api/v1/resources')->assertUnauthorized();
    $this->postJson('/api/v1/resources', [])->assertUnauthorized();
    $this->putJson('/api/v1/resources/1', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/resources/1')->assertUnauthorized();
}
```

---

## Pattern 4 — 403 Role/Policy Tests

```php
#[Test]
public function viewer_cannot_create_resource(): void
{
    $user = $this->viewerUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/resources', $this->validPayload())
        ->assertForbidden(); // 403
}
```

---

## Pattern 5 — 404 Cross-Team Isolation Tests

**Important:** Models using `HasTeamFilters` return **404** (not 403) for cross-team access
because the global scope makes the record invisible before any policy check.

```php
#[Test]
public function cross_team_resource_is_not_visible(): void
{
    $userA = $this->adminUser();
    $userB = $this->adminUser();

    $resourceB = SomeModel::factory()->create(['team_id' => $userB->current_team_id]);

    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/resources/{$resourceB->id}")
        ->assertNotFound(); // 404 — NOT 403
}
```

**Models with `HasTeamFilters` (→ 404):**
```bash
grep -rn "HasTeamFilters" app/Models/ --include="*.php" -l
```
Currently: Machine, Alert, Geofence, Report, MineArea, FuelTransaction, MaintenanceRecord (verify)

**Models WITHOUT `HasTeamFilters` (check the policy — may be 403):**
Read the controller's `$this->authorize()` call and the policy's `view()` method.

---

## Pattern 6 — List Isolation Tests

```php
#[Test]
public function index_returns_only_own_team_records(): void
{
    $userA = $this->adminUser();
    $userB = $this->adminUser();

    SomeModel::factory()->count(3)->create(['team_id' => $userA->current_team_id]);
    SomeModel::factory()->count(7)->create(['team_id' => $userB->current_team_id]);

    $this->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/resources')
        ->assertOk()
        ->assertJsonCount(3, 'data'); // only userA's 3, not userB's 7
}
```

---

## Pattern 7 — 422 Validation Tests

```php
#[Test]
public function store_requires_name_field(): void
{
    $user = $this->adminUser();
    $payload = $this->validPayload();
    unset($payload['name']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/resources', $payload)
        ->assertUnprocessable()                    // 422
        ->assertJsonValidationErrors(['name']);
}

#[Test]
public function store_rejects_invalid_type(): void
{
    $user = $this->adminUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/resources', array_merge($this->validPayload(), [
            'type' => 'not_valid',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
}
```

---

## Pattern 8 — 429 Rate Limit Tests

```php
use Illuminate\Support\Facades\RateLimiter;

#[Test]
public function endpoint_is_throttled_after_ten_requests(): void
{
    Queue::fake();
    RateLimiter::clear('limiter-name'); // CRITICAL — clear between test runs

    $user = $this->adminUser();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/resource', $this->validPayload())
            ->assertAccepted(); // or assertCreated(), assertOk()
    }

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/resource', $this->validPayload())
        ->assertStatus(429);
}
```

Rate limiter names (from `app/Providers/AppServiceProvider.php`):

| Route | Limiter Name | Limit |
|---|---|---|
| All `/api/v1` | `api` | 60/min |
| `POST /api/v1/reports` | `reports` | 10/min |
| `GET /api/v1/reports/{id}/download` | `downloads` | 10/min |

---

## Pattern 9 — Queue + Job Tests

```php
use Illuminate\Support\Facades\Queue;

#[Test]
public function creating_resource_dispatches_job(): void
{
    Queue::fake();
    $user = $this->adminUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/resources', $this->validPayload())
        ->assertCreated();

    Queue::assertPushed(MyJob::class, function ($job) use ($user) {
        return $job->userId === $user->id;
    });
}
```

---

## Pattern 10 — Event + Listener Tests

```php
use Illuminate\Support\Facades\Event;

#[Test]
public function action_fires_expected_event(): void
{
    Event::fake([NotificationCreated::class]);

    MyEvent::dispatch($model);

    Event::assertDispatched(NotificationCreated::class);
    // or
    Event::assertNotDispatched(NotificationCreated::class);
}
```

---

## Pattern 11 — Livewire Component Tests

```php
use Livewire\Livewire;

#[Test]
public function component_renders_for_authenticated_user(): void
{
    $user = $this->adminUser();

    Livewire::actingAs($user)
        ->test(MyComponent::class)
        ->assertOk()
        ->assertSee('Expected Text');
}

#[Test]
public function component_action_updates_state(): void
{
    $user = $this->adminUser();

    Livewire::actingAs($user)
        ->test(MyComponent::class)
        ->call('myAction', $param)
        ->assertSet('myProperty', $expectedValue);
}
```

---

## Running Tests

```bash
# Single file
php artisan test --compact tests/Feature/FeatureTest.php

# Single test method  
php artisan test --compact --filter=test_method_name_without_test_prefix
# Example: --filter=unauthenticated_requests_are_rejected

# All tests
php artisan test --compact

# With verbose failure details
php artisan test 2>&1 | grep -A 15 "FAIL"

# Current baseline: 279 passed, 6 skipped, 648 assertions
```

---

## Guardrails

- **Never delete a test** — tests are canonical documentation of expected behaviour
- **Never use `/** @test */`** — use `#[Test]` attribute (PHPUnit 11)
- **Never use `Notification::fake()`** unless you want to suppress ALL notifications — use `Queue::fake()` or `Event::fake()` for specific fakes
- **Never assert 403 for HasTeamFilters models** — they return 404 (the model is invisible at binding)
- **Always call `RateLimiter::clear()`** before rate limit tests — test isolation
- **Always `Queue::fake()`** before tests that trigger jobs, otherwise real jobs run in tests
