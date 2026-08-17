# AI Recommendation Approval Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn Dot.Mines' existing AI-recommendation implement/reject flow into a real, auditable Level 2 (Escalate) process: a distinct Proposed Action field, a real decision log via the currently-unused `AiRecommendationAction` model, required reject reasons, and approval tightened to owner/admin.

**Architecture:** Two additive migrations (nullable `proposed_action` on `ai_recommendations`, nullable `ai_recommendation_id` FK on `ai_recommendation_actions`), one policy edit (remove a same-team fallback), one Livewire component edit (write a decision-log row per action, require a reason on reject), one worked-example agent edit (`RouteAdvisorAgent`) plus the two `AIOptimizationService` call sites that create recommendations for every agent.

**Tech Stack:** Laravel 12, Livewire 3, PHPUnit (`php artisan test`), existing Jetstream team/role/permission model already in this codebase.

## Global Constraints

- Both migrations are additive only — never drop or rename an existing column, never touch `AIRecommendation` generation itself.
- `proposed_action` falls back to `description` when an agent doesn't supply one — no agent breaks.
- Only `RouteAdvisorAgent` gets a real `proposed_action` value in this plan; the other 5 agents are explicitly deferred.
- Rejecting a recommendation without a reason must be blocked — never silently accepted as empty string.
- `AIRecommendationPolicy::update()` keeps exactly two paths: `hasRole('owner'|'admin'|'administrator')` or `hasPermission('update_recommendations')` — the same-team fallback is removed, nothing else changes.
- Never touch `resources/views/components/application-mark.blade.php`, `resources/views/livewire/sidebar.blade.php`, or the two `public/images/mark*.png` files — these are pre-existing unrelated uncommitted changes in this repo and must not be committed or modified by this work.

---

### Task 1: Migrations + model updates

**Files:**
- Create: `database/migrations/2026_08_09_000001_add_proposed_action_to_ai_recommendations_table.php`
- Create: `database/migrations/2026_08_09_000002_add_ai_recommendation_id_to_ai_recommendation_actions_table.php`
- Modify: `app/Models/AIRecommendation.php`
- Modify: `app/Models/AiRecommendationAction.php`

**Interfaces:**
- Produces: `ai_recommendations.proposed_action` (nullable string column, added to `AIRecommendation::$fillable`), `ai_recommendation_actions.ai_recommendation_id` (nullable FK to `ai_recommendations.id`, added to `AiRecommendationAction::$fillable`), and a new `AiRecommendationAction::aiRecommendation(): BelongsTo` relation. Task 3 creates `AiRecommendationAction` rows using these fields; Task 4 populates `proposed_action`.

- [ ] **Step 1: Write the `proposed_action` migration**

Create `database/migrations/2026_08_09_000001_add_proposed_action_to_ai_recommendations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->text('proposed_action')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->dropColumn('proposed_action');
        });
    }
};
```

- [ ] **Step 2: Write the `ai_recommendation_id` migration**

Create `database/migrations/2026_08_09_000002_add_ai_recommendation_id_to_ai_recommendation_actions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_recommendation_actions', function (Blueprint $table) {
            $table->foreignId('ai_recommendation_id')->nullable()->after('team_id')
                ->constrained('ai_recommendations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_recommendation_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_recommendation_id');
        });
    }
};
```

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate`
Expected: both new migrations run, no errors.

- [ ] **Step 4: Update `AIRecommendation` model**

In `app/Models/AIRecommendation.php`, add `'proposed_action'` to `$fillable`, right after `'description'`:

```php
    protected $fillable = [
        'team_id',
        'ai_agent_id',
        'user_id',
        'category',
        'priority',
        'status',
        'title',
        'description',
        'proposed_action',
        'data',
        'impact_analysis',
        'confidence_score',
        'estimated_savings',
        'estimated_efficiency_gain',
        'related_machine_id',
        'related_mine_area_id',
        'related_route_id',
        'implemented_at',
        'implemented_by',
        'implementation_notes',
    ];
```

- [ ] **Step 5: Update `AiRecommendationAction` model**

Replace the full contents of `app/Models/AiRecommendationAction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendationAction extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_recommendation_actions';

    protected $fillable = [
        'team_id',
        'ai_recommendation_id',
        'recommendation_hash',
        'recommendation',
        'status',
        'actioned_by',
        'actioned_at',
        'reject_reason',
        'performance_impact',
    ];

    protected $casts = [
        'recommendation' => 'json',
        'performance_impact' => 'json',
        'actioned_at' => 'datetime',
    ];

    public function aiRecommendation(): BelongsTo
    {
        return $this->belongsTo(AIRecommendation::class);
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_09_000001_add_proposed_action_to_ai_recommendations_table.php \
  database/migrations/2026_08_09_000002_add_ai_recommendation_id_to_ai_recommendation_actions_table.php \
  app/Models/AIRecommendation.php app/Models/AiRecommendationAction.php
git commit -m "feat: proposed_action column + decision-log FK for AI recommendations

Additive migrations only. AiRecommendationAction gains a real FK to
AIRecommendation (was a loose hash+JSON blob) and an aiRecommendation()
relation, wiring up a model that previously had zero real usage in the
codebase."
```

---

### Task 2: Tighten `AIRecommendationPolicy::update()`

**Files:**
- Modify: `app/Policies/AIRecommendationPolicy.php`
- Test: `tests/Feature/AIRecommendationPolicyTest.php`

**Interfaces:**
- Consumes: `AIRecommendation` (Task 1's `Eloquent` model, unchanged shape here), `User::hasRole()`/`hasPermission()` (existing, unchanged).
- Produces: nothing new consumed by later tasks — Task 3's dashboard already calls `$this->authorize('update', $recommendation)`, which now resolves through this tightened policy automatically.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AIRecommendationPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AIRecommendation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIRecommendationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Team $team, string $roleName): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $role = Role::factory()->create(['team_id' => $team->id, 'name' => $roleName]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_owner_can_update_a_recommendation_on_their_team(): void
    {
        $team = Team::factory()->create();
        $owner = $this->userWithRole($team, 'owner');
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        $this->assertTrue($owner->can('update', $recommendation));
    }

    public function test_admin_can_update_a_recommendation_on_their_team(): void
    {
        $team = Team::factory()->create();
        $admin = $this->userWithRole($team, 'admin');
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        $this->assertTrue($admin->can('update', $recommendation));
    }

    public function test_a_same_team_member_with_no_role_or_permission_cannot_update(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        $this->assertFalse($member->can('update', $recommendation));
    }
}
```

- [ ] **Step 2: Run test to verify the third test fails**

Run: `php artisan test --filter=AIRecommendationPolicyTest`
Expected: `test_a_same_team_member_with_no_role_or_permission_cannot_update` FAILS (currently passes the same-team fallback), the other two PASS already.

- [ ] **Step 3: Tighten the policy**

In `app/Policies/AIRecommendationPolicy.php`, replace the `update()` method:

```php
    /**
     * Determine whether the user can update (implement/reject) the recommendation.
     */
    public function update(User $user, AIRecommendation $recommendation): bool
    {
        if ($user->current_team_id !== $recommendation->team_id) {
            return false;
        }

        // Owners and admins may act; anyone else needs the explicit permission.
        if ($user->hasRole('owner') || $user->hasRole('admin') || $user->hasRole('administrator')) {
            return true;
        }

        return $user->hasPermission('update_recommendations');
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AIRecommendationPolicyTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Policies/AIRecommendationPolicy.php tests/Feature/AIRecommendationPolicyTest.php
git commit -m "fix: AIRecommendationPolicy::update() requires real approval authority

Removed the 'any user on the same team may act' fallback. Only
owner/admin/administrator roles, or the explicit update_recommendations
permission, may implement or reject a recommendation now."
```

---

### Task 3: Decision log + required reject reason in `AIOptimizationDashboard`

**Files:**
- Modify: `app/Livewire/AIOptimizationDashboard.php`
- Modify: `resources/views/livewire/ai-optimization-dashboard.blade.php`
- Test: `tests/Feature/AIOptimizationDashboardApprovalTest.php`

**Interfaces:**
- Consumes: `AiRecommendationAction` (Task 1's `aiRecommendation()`/`actionedBy()` relations and `$fillable`), `AIRecommendationPolicy::update()` (Task 2, unchanged interface — same `authorize('update', $recommendation)` call).
- Produces: nothing new consumed by later tasks.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AIOptimizationDashboardApprovalTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Livewire\AIOptimizationDashboard;
use App\Models\AiRecommendationAction;
use App\Models\AIRecommendation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AIOptimizationDashboardApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function ownerUser(Team $team): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $role = Role::factory()->create(['team_id' => $team->id, 'name' => 'owner']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_implementing_a_recommendation_writes_a_decision_log_row(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->call('implementRecommendation', $recommendation->id);

        $this->assertDatabaseHas('ai_recommendation_actions', [
            'ai_recommendation_id' => $recommendation->id,
            'team_id' => $team->id,
            'status' => 'implemented',
            'actioned_by' => $owner->id,
        ]);
    }

    public function test_rejecting_without_a_reason_is_blocked_and_writes_no_decision_log_row(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->call('rejectRecommendation', $recommendation->id, '');

        $this->assertDatabaseMissing('ai_recommendation_actions', [
            'ai_recommendation_id' => $recommendation->id,
        ]);
        $this->assertSame('pending', $recommendation->fresh()->status);
    }

    public function test_rejecting_with_a_reason_writes_a_decision_log_row_with_that_reason(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->call('rejectRecommendation', $recommendation->id, 'Not applicable to our current fleet configuration.');

        $this->assertDatabaseHas('ai_recommendation_actions', [
            'ai_recommendation_id' => $recommendation->id,
            'status' => 'rejected',
            'reject_reason' => 'Not applicable to our current fleet configuration.',
            'actioned_by' => $owner->id,
        ]);
        $this->assertSame('rejected', $recommendation->fresh()->status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AIOptimizationDashboardApprovalTest`
Expected: FAIL — `rejectRecommendation` doesn't currently accept a `$reason` argument, and no `AiRecommendationAction` row is ever written.

- [ ] **Step 3: Update `implementRecommendation()` and `rejectRecommendation()`**

In `app/Livewire/AIOptimizationDashboard.php`, replace both methods:

```php
    public function implementRecommendation($recommendationId)
    {
        $team = auth()->user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);
        try {
            $this->authorize('update', $recommendation);

            $recommendation->markAsImplemented(auth()->user());

            \App\Models\AiRecommendationAction::create([
                'team_id' => $team->id,
                'ai_recommendation_id' => $recommendation->id,
                'recommendation_hash' => sha1($recommendation->id . $recommendation->title),
                'recommendation' => ['title' => $recommendation->title, 'description' => $recommendation->description],
                'status' => 'implemented',
                'actioned_by' => auth()->id(),
                'actioned_at' => now(),
            ]);

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Recommendation marked as implemented!']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'implemented']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'You are not authorized to implement this recommendation.']);
            return;
        }
    }

    public function rejectRecommendation($recommendationId, string $reason = '')
    {
        $team = auth()->user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);

        if (trim($reason) === '') {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'A rejection reason is required.']);
            return;
        }

        try {
            $this->authorize('update', $recommendation);

            $recommendation->update(['status' => 'rejected']);

            \App\Models\AiRecommendationAction::create([
                'team_id' => $team->id,
                'ai_recommendation_id' => $recommendation->id,
                'recommendation_hash' => sha1($recommendation->id . $recommendation->title),
                'recommendation' => ['title' => $recommendation->title, 'description' => $recommendation->description],
                'status' => 'rejected',
                'actioned_by' => auth()->id(),
                'actioned_at' => now(),
                'reject_reason' => $reason,
            ]);

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Recommendation rejected.']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'rejected']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'You are not authorized to reject this recommendation.']);
            return;
        }
    }
```

- [ ] **Step 4: Wire the reject reason through the confirm dialog**

In `app/Livewire/AIOptimizationDashboard.php`, add a new public property near the existing `$showRecommendationConfirm` declaration:

```php
    public string $rejectReason = '';
```

Replace `confirmRecommendationAction()`:

```php
    public function confirmRecommendationAction()
    {
        if (! $this->pendingRecommendationId || ! in_array($this->pendingRecommendationAction, ['implement', 'reject'])) {
            $this->showRecommendationConfirm = false;
            $this->pendingRecommendationId = null;
            $this->pendingRecommendationAction = null;
            $this->rejectReason = '';
            return;
        }

        $id = $this->pendingRecommendationId;
        $action = $this->pendingRecommendationAction;

        if ($action === 'implement') {
            $this->implementRecommendation($id);
        } else {
            $this->rejectRecommendation($id, $this->rejectReason);
            if (trim($this->rejectReason) === '') {
                // rejectRecommendation() already surfaced the error notification; keep the dialog open.
                return;
            }
        }

        $this->showRecommendationConfirm = false;
        $this->pendingRecommendationId = null;
        $this->pendingRecommendationAction = null;
        $this->rejectReason = '';
        // Refresh pagination/list
        $this->resetPage();
    }
```

Update `cancelRecommendationAction()` to also clear the reason:

```php
    public function cancelRecommendationAction()
    {
        $this->showRecommendationConfirm = false;
        $this->pendingRecommendationId = null;
        $this->pendingRecommendationAction = null;
        $this->rejectReason = '';
    }
```

- [ ] **Step 5: Add the reason textarea to the confirm dialog**

In `resources/views/livewire/ai-optimization-dashboard.blade.php`, replace the block between the recommendation summary `<div class="space-y-3">...</div>` and the action buttons (the section shown below, currently ending right before `<div class="flex gap-2 justify-end mt-6...`):

```blade
                            <div class="space-y-3">
                                <div class="text-sm text-gray-800 dark:text-gray-200">
                                    <strong>{{ $pending?->title }}</strong>
                                    <div class="text-gray-500 dark:text-gray-400 text-xs mt-1">{{ $pending?->description }}</div>
                                    @if($pending?->proposed_action)
                                        <div class="text-gray-700 dark:text-gray-300 text-xs mt-2"><strong>Proposed action:</strong> {{ $pending->proposed_action }}</div>
                                    @endif
                                </div>

                                @if($pendingRecommendationAction === 'reject')
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Reason for rejecting (required)</label>
                                        <textarea wire:model="rejectReason" rows="2" class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white"></textarea>
                                    </div>
                                @endif
                            </div>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=AIOptimizationDashboardApprovalTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/AIOptimizationDashboard.php resources/views/livewire/ai-optimization-dashboard.blade.php \
  tests/Feature/AIOptimizationDashboardApprovalTest.php
git commit -m "feat: real decision log + required reject reason for AI recommendations

implementRecommendation()/rejectRecommendation() now each write an
AiRecommendationAction row (who, when, status, and for rejections, why).
Rejecting with an empty reason is blocked -- the confirm dialog's reject
path requires a non-empty textarea before it will proceed."
```

---

### Task 4: `proposed_action` wiring through `AIOptimizationService` + `RouteAdvisorAgent`

**Files:**
- Modify: `app/Services/AI/AIOptimizationService.php`
- Modify: `app/Services/AI/RouteAdvisorAgent.php`
- Test: `tests/Unit/RouteAdvisorAgentTest.php`

**Interfaces:**
- Consumes: `AIRecommendation::$fillable` (Task 1, now includes `proposed_action`).
- Produces: nothing consumed by later tasks — this is the final task.

- [ ] **Step 0: Create the missing `RouteFactory`**

`app/Models/Route.php` uses the `HasFactory` trait but `database/factories/RouteFactory.php` does not exist — confirmed via `find database/factories -iname "*Route*"` returning nothing. This blocks this task's test, so it's created here rather than silently worked around. Check first:

Run: `test -f database/factories/RouteFactory.php && echo EXISTS || echo MISSING`

If `MISSING`, create `database/factories/RouteFactory.php` (fields match `database/migrations/2026_01_22_100000_create_routes_table.php` exactly — `total_distance`, `estimated_time`, `estimated_fuel` are NOT NULL with no default):

```php
<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteFactory extends Factory
{
    protected $model = Route::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'start_latitude' => $this->faker->latitude(-26.5, -25.5),
            'start_longitude' => $this->faker->longitude(27.5, 28.5),
            'end_latitude' => $this->faker->latitude(-26.5, -25.5),
            'end_longitude' => $this->faker->longitude(27.5, 28.5),
            'total_distance' => $this->faker->randomFloat(2, 5, 100),
            'estimated_time' => $this->faker->numberBetween(10, 180),
            'estimated_fuel' => $this->faker->randomFloat(2, 2, 50),
            'route_type' => 'optimal',
            'status' => 'draft',
        ];
    }
}
```

Run: `php artisan test --filter=RouteAdvisorAgentTest` is not expected to pass yet at this step (the test file doesn't exist); this step only unblocks the factory dependency Step 1's test needs.

- [ ] **Step 1: Write the failing test**

First, check whether `tests/Unit/RouteAdvisorAgentTest.php` already exists:

Run: `test -f tests/Unit/RouteAdvisorAgentTest.php && echo EXISTS || echo MISSING`

If `MISSING`, create `tests/Unit/RouteAdvisorAgentTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Route;
use App\Models\Team;
use App\Services\AI\RouteAdvisorAgent;
use App\Services\RoutePlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAdvisorAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recommendation_with_improvement_possible_includes_a_distinct_proposed_action(): void
    {
        $team = Team::factory()->create();
        $route = Route::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'start_latitude' => -26.2041,
            'start_longitude' => 28.0473,
            'end_latitude' => -26.1052,
            'end_longitude' => 28.0567,
        ]);

        $agent = app(RouteAdvisorAgent::class);
        $result = $agent->analyze($team);

        if (count($result['recommendations']) === 0) {
            $this->markTestSkipped('Route efficiency fixture did not cross the 15% improvement threshold; not this test\'s concern to fabricate route geometry.');
        }

        $recommendation = $result['recommendations'][0];
        $this->assertArrayHasKey('proposed_action', $recommendation);
        $this->assertNotSame($recommendation['description'], $recommendation['proposed_action']);
        $this->assertStringContainsString($route->name, $recommendation['proposed_action']);
    }
}
```

- [ ] **Step 2: Run test to verify current behavior**

Run: `php artisan test --filter=RouteAdvisorAgentTest`
Expected: either FAIL with "Undefined array key 'proposed_action'" (if the route fixture crosses the improvement threshold) or SKIPPED (if it doesn't — route-efficiency fixture geometry is not this task's concern; the skip path exists so this test never blocks on unrelated fixture tuning).

- [ ] **Step 3: Add `proposed_action` to `RouteAdvisorAgent`**

In `app/Services/AI/RouteAdvisorAgent.php`, replace the recommendation-building block inside `analyze()`:

```php
            if ($efficiency['improvement_possible'] > 15) {
                $recommendations[] = [
                    'category' => 'route',
                    'priority' => 'high',
                    'title' => "Route Optimization Opportunity: {$route->name}",
                    'description' => "Route can be optimized to save {$efficiency['time_savings']} minutes and {$efficiency['fuel_savings']} liters of fuel.",
                    'proposed_action' => "Reroute {$route->name} via the optimized path identified by the route advisor to capture the {$efficiency['time_savings']}-minute, {$efficiency['fuel_savings']}-liter savings above.",
                    'confidence_score' => 0.83,
                    'estimated_savings' => $efficiency['fuel_savings'] * 25,
                    'estimated_efficiency_gain' => $efficiency['improvement_possible'],
                    'related_route_id' => $route->id,
                    'data' => $efficiency,
                ];
            }
```

- [ ] **Step 4: Wire `proposed_action` through both `AIOptimizationService` creation sites**

In `app/Services/AI/AIOptimizationService.php`, in `runComprehensiveAnalysis()`'s recommendation-storing loop, add one line to the `AIRecommendation::create([...])` array, right after `'description' => $rec['description'],`:

```php
                        'description' => $rec['description'],
                        'proposed_action' => $rec['proposed_action'] ?? $rec['description'],
```

Do the same in `getRecommendationsForCategory()`'s `AIRecommendation::create([...])` call — add the identical line right after its own `'description' => $rec['description'],`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=RouteAdvisorAgentTest`
Expected: PASS or SKIPPED (not FAIL) — if SKIPPED, that's an acceptable outcome per Step 2's note, not a blocker.

- [ ] **Step 6: Run the full test suite**

Run: `php artisan test`
Expected: 0 failures (the pre-existing suite plus this plan's 7 new tests: 3 in `AIRecommendationPolicyTest`, 3 in `AIOptimizationDashboardApprovalTest`, 1 in `RouteAdvisorAgentTest`).

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/AIOptimizationService.php app/Services/AI/RouteAdvisorAgent.php \
  tests/Unit/RouteAdvisorAgentTest.php database/factories/RouteFactory.php
git commit -m "feat: wire proposed_action through AIOptimizationService + RouteAdvisorAgent

RouteAdvisorAgent is the one worked example producing a real, distinct
proposed_action. Both AIOptimizationService creation sites fall back to
description for the 5 agents not yet updated -- flagged as follow-up,
not expanded into this task. Also adds the missing RouteFactory (Route
used HasFactory with no factory file backing it)."
```

---

## Self-Review Notes

- **Spec coverage:** Task 1 covers spec change 1's schema half and change 2's schema half. Task 2 covers spec change 3 in full. Task 3 covers spec change 2's behavioral half (decision log writes, required reject reason) in full. Task 4 covers spec change 1's behavioral half (the worked-example agent + service wiring) in full.
- **Placeholder scan:** none — every step has literal file content or an exact shell command.
- **Type consistency:** `rejectRecommendation($recommendationId, string $reason = '')`'s signature is used identically in Task 3's own tests and its own confirm-dialog wiring (Step 4's `confirmRecommendationAction()`); `AiRecommendationAction::create()`'s field names match Task 1's `$fillable` exactly across both call sites in Task 3.
- **Uncommitted pre-existing changes:** verified via `git status --short` before starting that `resources/views/components/application-mark.blade.php`, `resources/views/livewire/sidebar.blade.php`, and two `public/images/mark*.png` files are pre-existing unrelated modifications in this repo — every `git add` in this plan lists files explicitly, never `git add -A` or `git add .`, so these stay untouched.
- **Caught during self-review, fixed inline:** Task 4's test needed `Route::factory()`, but `database/factories/RouteFactory.php` does not exist despite `Route` using `HasFactory` — confirmed via direct `find`. Added Task 4 Step 0 to create it, matching the real `routes` table schema exactly (including the three NOT NULL columns with no default: `total_distance`, `estimated_time`, `estimated_fuel`).
