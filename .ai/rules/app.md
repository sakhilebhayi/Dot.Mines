---
paths:
  - 'app/**'
---

# App

## Actions pattern for write operations
Business write operations live in `app/Actions/{Domain}/` — one class, one operation, a typed `execute()` with explicit input/output. Controllers and Livewire components orchestrate (validate → authorize → call Action → respond); Actions own the business rules; Services own external integrations and cross-cutting infrastructure. Do not add repositories over Eloquent or interfaces with a single implementation. Introduced by the 2026-08-22 refactor program (see docs/superpowers/specs/2026-08-22-codebase-refactor-ai-readiness-design.md); adopted progressively — follow it for all new write paths and whenever touching an old one.

## Narrow Auth::user()/Request::user() with instanceof User, never stubs or @var
This larastan setup types Auth::user(), auth()->user(), and $request->user() as the bare Authenticatable contract (the facade @method docblock pins it; PHPStan stub files for Guard::user()/Request::user() are rejected by the stub validator with a non-ignorable "invalid return type App\Models\User"). The working pattern is local narrowing: `$user = Auth::user(); if (! $user instanceof \App\Models\User) { abort/return; }` — after which all User properties and relations resolve. API controllers use a private `currentTeamId(Request $request): int` helper built on the same narrowing. phpstan runs BARE since R6 (no baseline — AnalyzerBaselineRatchetTest asserts the file stays deleted), so any regression here fails CI immediately.
