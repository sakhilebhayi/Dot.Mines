# Dot.Mines writes its actions to the ecosystem intelligence loop

**Date:** 2026-10-16. Owner decision: platform-level service token (like the guardian), not per-team configuration. Companion: Dot.Central's `docs/dot-central/14-dot-memory-integration.md` and its issue I-33.

## 1. Goal

Dot.Memory's recall for a machine should carry what the mine did, not only what the control room did. Dot.Mines records its own observations, decisions, actions and outcomes under the same subject vocabulary Dot.Central uses: `equipment` with `subject_id` = the machine id, `mine:<team id>` for team-level items. `platform` is `dot-mines`; signatures are `dot-mines:` prefixed.

## 2. Connection

`config/services.php` key `memory`: `url` (`DOT_MEMORY_URL`) and `token` (`DOT_MEMORY_TOKEN`), one service token minted with Dot.Memory's `provision-service-token.yml` as `service=dot-mines`. Unset token = integration inert (no rows, no requests). Records carry `team_id` so each mine's records stay attributable.

## 3. What is recorded (derived, never typed)

| Event | Stage | Source | Key fields |
|---|---|---|---|
| Alert triggered | observation | `AlertTriggered` event | signature `dot-mines:alert:<priority>`; title, type |
| Alert acknowledged | action | alert model observer (status → acknowledged; covers screen and API) | `alert.acknowledge`, executor `dot-mines`, succeeded |
| Alert resolved | action + outcome | alert model observer (status → resolved) | `alert.resolve`; outcome improved, measure `alert.time_to_clear_minutes` |
| Machine offline | observation | `MachineOffline` event | signature `dot-mines:machine:offline`; reason |
| Machine back online | outcome | `MachineStatusMonitoringJob` transition from offline | verdict improved, measure `machine.minutes_offline` (from the last offline observation in the outbox) |
| Operator assigned | action | `OperatorAssigned` event | `operator.assign`, succeeded; override → approval_status approved, overridden failures in detail |
| Operator unassigned | action | `OperatorUnassigned` event | `operator.unassign`, reason |
| Maintenance booked | decision + action | maintenance record observer (created) | recommendation = title; autonomy `execute`; risk by priority; `maintenance.book` |
| Maintenance completed | outcome | maintenance record observer (status → completed) | verdict improved when `machine_operational`, else inconclusive; measure `maintenance.labor_hours` |
| AI recommendation raised | decision | recommendation model observer (created) | autonomy `recommend`; confidence from priority; requires_approval true; subject equipment when `related_machine_id`, else `mine` |
| AI recommendation implemented / rejected | action | recommendation action observer (created) | `recommendation.implement` / `.reject`, approval approved / rejected, reject reason in detail |

`event_id` is keyed by subject state (`dot-mines:alert:<id>:resolve`), so a repeated save never duplicates. `loop_id` groups a thread: `dot-mines:alert:<id>`, `dot-mines:machine:<id>`, `dot-mines:assignment:<id>`, `dot-mines:maintenance:<id>`, `dot-mines:recommendation:<id>`.

## 4. Sending

Table `memory_outbox` (team_id, stage, event_id unique, payload json, attempts, last_error, next_attempt_at, sent_at). Observers and listeners insert a row and return; nothing waits on Memory. `memory:flush` runs every minute from the scheduler (no queued job: the queue runs `--tries=1` from cron and a resident worker does not exist), sends due rows in order, backs off 1, 5, 15, 60 minutes, gives up after 20 attempts (row kept, counted in the command output), stops the run on the first unreachable answer, and disables sending for the tick on 401/403 with a warning in the log. `memory:prune` deletes sent rows older than 30 days. `MemoryClient` is typed and never puts the token in a message.

## 5. Dot.Central follow-up

`MemoryClient::context()` drops the `platform` filter so the recall strip shows both platforms' records for a machine.

## 6. Tests

Recording: one test per table row, asserting the outbox payload. Flush: send, backoff, give up, credential rejected, inert without token. Client: stage endpoints, bearer, error typing. Gates: full suite, Pint, PHPStan max, Psalm (no baselines).
