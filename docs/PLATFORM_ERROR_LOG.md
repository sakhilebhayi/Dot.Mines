# Platform Error Log

> Maintained by the **Platform Guardian** agent (`platform-guardian.agent.md`).
> Every resolved incident is recorded here. Newest entries at the top.
> Format defined in the agent's `PLATFORM_ERROR_LOG.md Protocol` section.

---

## Legend

| Status | Meaning |
|---|---|
| ✅ Resolved | Fix applied, tests pass, committed |
| 🔄 In Progress | Being actively worked |
| ⚠️ Escalated | Needs human developer review |
| 📋 Logged | Captured, not yet investigated |

---

## [2026-06-06] E-001 — Livewire `dispatchBrowserEvent` Across 10 Components

**Reported by:** Static analysis / CI  
**Severity:** High  
**Component:** All Livewire components  
**Status:** ✅ Resolved (commit `7227f8f`)

### Symptom
Livewire 3 does not support `$this->dispatchBrowserEvent()`. All 10 components were using the
Livewire 2 API, causing silent failures in browser event dispatching.

### Root Cause
The application was originally written for Livewire 2 and migrated to Livewire 3 without updating
event dispatch calls. Livewire 3 replaced `dispatchBrowserEvent('name', ['key' => $val])` with
`dispatch('name', key: $val)`.

### Fix Applied
- Migrated all 10 Livewire components from `dispatchBrowserEvent` to `dispatch`
- Commit: `7227f8f`

### Prevention
- PHPStan `ignoreErrors` rule remains for backward compatibility but new calls are flagged in
  code review
- Platform Guardian known pattern E-001 documents the fix path

### Knowledge Update
Added E-001 to Known Error Patterns with automated grep-based detection and fix procedure.

---

## [2026-06-06] — Team Invitation Email Not Sending

**Reported by:** User (Settings page)  
**Severity:** High  
**Component:** `Settings.php` Livewire / `InviteTeamMember` action / `TeamInvitationMail`  
**Status:** ✅ Resolved (commit `2158463`)

### Symptom
Inviting a new user from the Settings page silently created a user with a temporary password
instead of sending a Jetstream team invitation email.

### Root Cause
`Settings::inviteUser()` had a `// TODO` comment where the invitation logic should have been.
It directly created a `User` record instead of calling `InviteTeamMember` action. Additionally,
custom roles (`fleet_manager`, `operator`, `viewer`) were not registered with Jetstream, causing
role validation to fail. `TeamInvitation.$fillable` was also missing `team_id`.

### Fix Applied
- `Settings.php`: `inviteUser()` now calls `InviteTeamMember` action
- `JetstreamServiceProvider`: Added `fleet_manager`, `operator`, `viewer` role registrations
- `AddTeamMember`: Assigns RBAC role after attaching user
- `TeamInvitation`: Added `team_id` to `$fillable`

### Prevention
Feature test `InvitationEmailTest` verifies the full flow end-to-end.

### Knowledge Update
Documented team invitation flow in repo memory. Added RBAC role registration requirement.

---

## [2026-06-06] — Notification Type Enum Too Restrictive

**Reported by:** Developer (NotificationService integration)  
**Severity:** Medium  
**Component:** `notifications` table, `NotificationService`  
**Status:** ✅ Resolved (commit `59c3aa7`)

### Symptom
`NotificationService::dispatch()` could not store new notification types (e.g., `TYPE_GEOFENCE_BREACH`,
`TYPE_AI_PREDICTION`) because the `type` and `alert_level` columns were restricted MySQL enums.

### Root Cause
Original migration defined `notifications.type` and `alert_level` as strict `enum()` columns,
blocking any new type strings from being inserted.

### Fix Applied
- Migration `2026_06_06_024700_extend_notifications_type_and_delivery_logs.php`
- Changed both columns to `string(100)` / `string(20)`
- Created `notification_delivery_logs` table for per-user delivery tracking

### Prevention
All future notification type columns should use `string()` not `enum()` unless the set is truly
immutable. Added to architecture notes.

### Knowledge Update
`App\Models\Notification` uses `string` type/alert_level columns. `NotificationDeliveryLog` model
tracks per-user delivery status and errors.

---

<!-- New entries go above this line -->
<!-- Format:
## [YYYY-MM-DD] E-0NN — Short Title
**Reported by:** ...
**Severity:** Critical | High | Medium | Low
**Component:** ...
**Status:** ✅ Resolved | 🔄 In Progress | ⚠️ Escalated

### Symptom
### Root Cause
### Fix Applied
### Prevention
### Knowledge Update
-->
