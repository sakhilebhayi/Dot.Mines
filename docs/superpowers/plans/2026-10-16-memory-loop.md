# Memory Loop (Dot.Mines) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dot.Mines records its alerts, machine offline/online, operator assignments, maintenance and AI recommendations into Dot.Memory's intelligence loop under the shared machine subjects.

**Architecture:** `MemoryLoopRecorder` builds payloads and writes `memory_outbox` rows; model observers (Alert, MaintenanceRecord, AIRecommendation, AiRecommendationAction) and an event subscriber (AlertTriggered, MachineOffline, OperatorAssigned, OperatorUnassigned) call it; `MachineStatusMonitoringJob` calls it on the offline→online transition; `memory:flush` sends every minute; `MemoryClient` talks to Dot.Memory. Platform-level token in `config/services.php`.

**Tech Stack:** Laravel 12, PHPStan max, Psalm, PHPUnit 11, `Http::fake()`.

## Global Constraints
- Spec: `docs/superpowers/specs/2026-10-16-memory-loop-design.md`. `.ai/rules/app.md`: narrow `Auth::user()` with `instanceof User`; no baselines; `.ai/rules/webhooks.md`: queued work must never rely on retries (hence a scheduled command, not a job).
- Token only from env; never logged or in exception messages.

### Task 1: Table, model, config
- Create `database/migrations/2026_10_16_000003_create_memory_outbox_table.php`, `app/Models/MemoryOutboxRow.php` (table `memory_outbox`, `MAX_ATTEMPTS = 20`, `BACKOFF_MINUTES = [1,5,15,60]`, scope `due()`, `backoff(): Carbon`), `config/services.php` `memory` => `['url' => env('DOT_MEMORY_URL'), 'token' => env('DOT_MEMORY_TOKEN'), 'timeout' => 10]`, `.env.example` keys.
- Test `tests/Feature/Memory/MemoryOutboxRowTest.php`: due scope and backoff.

### Task 2: Client
- Create `app/Services/Memory/MemoryClient.php` (`record(string $stage, array $payload): array`; stages event/decision/action/outcome → `/api/intelligence/*`; `MemoryUnavailable`, `MemoryCredentialRejected`, `MemoryRefused` exceptions in `app/Services/Memory/Exceptions`), `MemoryClientFactory::fromConfig(): ?MemoryClient` (null without url/token).
- Test `tests/Unit/Services/Memory/MemoryClientTest.php`.

### Task 3: Recorder, observers, subscriber, job hook
- Create `app/Services/Memory/MemoryLoopRecorder.php` with `queue(int $teamId, string $stage, array $payload): ?MemoryOutboxRow` (inert without config; idempotent by event_id) and one method per spec row: `alertTriggered(Alert)`, `alertAcknowledged(Alert)`, `alertResolved(Alert)`, `machineOffline(Machine, ?string $reason)`, `machineOnline(Machine, string $newStatus)`, `operatorAssigned(OperatorMachineAssignment)`, `operatorUnassigned(OperatorMachineAssignment)`, `maintenanceBooked(MaintenanceRecord)`, `maintenanceCompleted(MaintenanceRecord)`, `recommendationRaised(AIRecommendation)`, `recommendationActioned(AiRecommendationAction)`.
- Create observers in `app/Observers/Memory/` and `app/Listeners/MemoryLoopSubscriber.php`; register in `AppServiceProvider::boot` (`::observe`, `Event::subscribe`). Hook `MachineStatusMonitoringJob` online branch.
- Test `tests/Feature/Memory/MemoryLoopRecordingTest.php`: one method per row.

### Task 4: Flush and prune commands
- Create `app/Console/Commands/FlushMemoryOutbox.php` (`memory:flush`), `PruneMemoryOutbox.php` (`memory:prune`); schedule in `routes/console.php`.
- Test `tests/Feature/Memory/MemoryFlushTest.php`.

### Task 5: Wiki row 0.4.3, gates, PR. Dot.Central: drop the platform filter in `MemoryClient::context()` with its test, PR.
