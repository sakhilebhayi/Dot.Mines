---
paths:
  - 'app/**'
---

# App

## Actions pattern for write operations
Business write operations live in `app/Actions/{Domain}/` — one class, one operation, a typed `execute()` with explicit input/output. Controllers and Livewire components orchestrate (validate → authorize → call Action → respond); Actions own the business rules; Services own external integrations and cross-cutting infrastructure. Do not add repositories over Eloquent or interfaces with a single implementation. Introduced by the 2026-08-22 refactor program (see docs/superpowers/specs/2026-08-22-codebase-refactor-ai-readiness-design.md); adopted progressively — follow it for all new write paths and whenever touching an old one.

## Narrow Auth::user()/Request::user() with instanceof User, never stubs or @var
This larastan setup types Auth::user(), auth()->user(), and $request->user() as the bare Authenticatable contract (the facade @method docblock pins it; PHPStan stub files for Guard::user()/Request::user() are rejected by the stub validator with a non-ignorable "invalid return type App\Models\User"). The working pattern is local narrowing: `$user = Auth::user(); if (! $user instanceof \App\Models\User) { abort/return; }` — after which all User properties and relations resolve. API controllers use a private `currentTeamId(Request $request): int` helper built on the same narrowing. phpstan runs BARE since R6 (no baseline — AnalyzerBaselineRatchetTest asserts the file stays deleted), so any regression here fails CI immediately.

## Both analyzer baselines are deleted — new code must pass psalm and phpstan bare
psalm (errorLevel 1 + Laravel plugin) and phpstan (level max) both run with NO baseline since PRs #134/#145; AnalyzerBaselineRatchetTest fails CI if either baseline file reappears or psalm.xml regains errorBaseline. Fix findings, never re-baseline. Policy suppressions live commented in psalm.xml (ClassMustBeFinal, framework-dir dead-code exemptions, PossiblyUnusedReturnValue in Services/Models, PossiblyUnusedParam in Http/Policies/Notifications).

## Dual-analyzer patterns that satisfy psalm AND phpstan
Launder config() reads through App\Support\ApiPayload::str()/int()/strings() — psalm's plugin folds config keys to analysis-env literals (differs between CI and local) and marks guards dead. Split relation chains (`$rel = $this->x(); $rel->where(...); return $rel;`) — chained form gets conflicting HasMany-vs-Builder verdicts. Type with()/load() eager-closure params as `Relation` (scopes aren't visible through it — inline the where). Read groupBy/selectRaw aggregate aliases via getAttribute()/data_get, never as magic properties. @psalm-suppress docblocks bind to STATEMENTS, not expressions or chain segments. psalm reads @phpstan-return unless a @psalm-return is present. Stale duplicate @property blocks floating inside a model body shadow the class docblock — merge and delete them.

## Toasts go through NotifiesUser::notify(), never a raw dispatch
The toast host reads the NAMED Livewire dispatch shape (detail.type / detail.message). `$this->dispatch('notify', ['message' => …])` wraps the payload one level deeper (detail[0]) and renders a BLANK pill — 128 call sites across 15 components shipped that way, which is why "empty popup notifications" were reported platform-wide (first seen on Operator Fatigue). Always `$this->notify($message, $type)` from App\Livewire\Concerns\NotifiesUser: it enforces the shape, throws on a blank message or unknown type (success/error/warning/info), and NotificationContractTest forbids raw dispatch('notify' in app/ and the array shape in views. The host itself lives ONCE in components/toast-host.blade.php, included by both layouts (the two inline copies drifted twice: an id-collision bug and an info-contrast failure each fixed in only one). Durations are per type — success 5s, info 6s, warning 8s, error 10s — errors must survive long enough to be read.
