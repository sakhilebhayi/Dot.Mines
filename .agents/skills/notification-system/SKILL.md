---
name: notification-system
description: >
  Step-by-step playbook for maintaining, extending, and debugging the Mines notification system.
  Use when: wiring a new event into the notification pipeline, adding a new notification type,
  debugging the real-time bell, checking notification delivery logs, understanding how preferences
  work, or writing tests for notification behaviour.
argument-hint: 'Describe what you need to do with the notification system'
---

# Notification System Maintenance Playbook

## When to Use

- A new application event should trigger a user notification
- The notification bell is not showing unread count / not updating in real-time
- Notification emails are not being delivered
- You need to add a new notification type or level
- You need to test notification behaviour
- Notification preferences need to be respected in a new context

---

## Architecture Quick Reference

```
Application Event  →  Listener  →  NotificationService::notify*()
                                          ↓
                                   Notification (DB record)
                                   + notification_read (pivot)
                                   + SendNotificationEmailJob (queue: notifications)
                                   + NotificationCreated::dispatch() (Reverb broadcast)
                                          ↓
                                   NotificationBell listens via Echo
                                   → loadNotifications() → updates badge + list
```

---

## Procedure — Wire a New Event to the Notification System

### 1. Create the listener

```bash
php artisan make:class app/Listeners/SendMyEventNotification --no-interaction
```

```php
<?php

namespace App\Listeners;

use App\Events\MyEvent;
use App\Models\Notification;
use App\Services\NotificationService;

class SendMyEventNotification
{
    public function handle(MyEvent $event): void
    {
        NotificationService::notifyManagers(
            $event->team,
            Notification::TYPE_MACHINE,   // TYPE_ALERT | TYPE_MACHINE | TYPE_MINE_AREA | TYPE_FUEL | TYPE_SYSTEM
            Notification::LEVEL_WARNING,  // LEVEL_INFO | LEVEL_WARNING | LEVEL_HIGH | LEVEL_CRITICAL
            'Event Occurred',
            "Description: {$event->detail}",
        );
    }
}
```

### 2. Register in AppServiceProvider

```php
// app/Providers/AppServiceProvider.php
use App\Events\MyEvent;
use App\Listeners\SendMyEventNotification;

// In boot() or dedicated bootEventListeners() method:
Event::listen(MyEvent::class, SendMyEventNotification::class);
```

### 3. Add a test

```php
// tests/Feature/NotificationSystemTest.php — add this method:
#[Test]
public function my_event_dispatches_notification(): void
{
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    TeamRoleService::provisionTeam($team, $user);

    MyEvent::dispatch($team);

    $this->assertDatabaseHas('notifications', [
        'team_id' => $team->id,
        'type'    => Notification::TYPE_MACHINE,
        'level'   => Notification::LEVEL_WARNING,
    ]);
}
```

### 4. Run Pint and tests

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/NotificationSystemTest.php
```

---

## Procedure — Debug: Bell Not Showing Real-Time Updates

**Step 1 — Confirm the broadcast event fires**
```bash
grep -n "NotificationCreated::dispatch" app/Services/NotificationService.php
# Should show: NotificationCreated::dispatch($notification);
```

**Step 2 — Confirm the channel is authorized**
```bash
grep -n "team.*notifications" routes/channels.php
# Should show the channel definition and auth callback
```

**Step 3 — Confirm the Livewire component has the correct listener**
```bash
grep -n "On\|echo-private" app/Livewire/NotificationBell.php
# Should show: #[On('echo-private:team.{teamId}.notifications,notification.created')]
```

**Step 4 — Confirm Reverb is running**
```bash
ps aux | grep "reverb\|artisan" | grep -v grep
# If not running: php artisan reverb:start
```

**Step 5 — Check frontend Echo config**
```bash
grep -n "VITE_REVERB\|Echo\|reverb" .env resources/js/ -r 2>/dev/null | head -10
```

If frontend was recently changed → tell user: `npm run build` or `npm run dev`

---

## Procedure — Debug: Notification Emails Not Arriving

**Step 1 — Check mail configuration**
```bash
php artisan config:show mail
```
Expected: `MAIL_MAILER` set to `smtp` or `ses`; host, port, and credentials populated.

**Step 2 — Check delivery logs**
```bash
php artisan tinker --execute '
App\Models\NotificationDeliveryLog::where("status", "failed")->latest()->limit(5)->get([
    "user_id", "status", "error_message", "created_at"
]);
'
```

**Step 3 — Check jobs queued on notification queue**
```bash
php artisan tinker --execute '
DB::table("jobs")->where("queue", "notifications")->count();
'
```

**Step 4 — Check failed jobs**
```bash
php artisan queue:failed | head -20
php artisan tinker --execute '
DB::table("failed_jobs")->latest()->first();
'
```

**Step 5 — Retry failed jobs if safe**
```bash
php artisan queue:retry all
```

---

## Procedure — Add Notification Preference Gating

When a listener should respect `NotificationPreference`:

```php
use App\Models\NotificationPreference;

// Before calling NotificationService, check each target user's preference:
$pref = NotificationPreference::where([
    'user_id'           => $user->id,
    'team_id'           => $team->id,
    'notification_type' => $type,
])->first();

if ($pref) {
    if (! $pref->in_app_enabled && ! $pref->email_enabled) {
        return; // user opted out entirely
    }
    if (! $pref->isAboveMinLevel($level)) {
        return; // notification level too low for this user's threshold
    }
}
```

`isAboveMinLevel()` uses the `LEVEL_ORDER` constant:
```php
const LEVEL_ORDER = ['info' => 0, 'warning' => 1, 'high' => 2, 'critical' => 3];
```

---

## Test Commands Reference

```bash
# All notification tests
php artisan test --compact tests/Feature/NotificationSystemTest.php tests/Feature/NotificationBellComponentTest.php

# Count: should be 18 + 9 = 27 tests
php artisan test --compact tests/Feature/NotificationSystemTest.php | tail -3
php artisan test --compact tests/Feature/NotificationBellComponentTest.php | tail -3

# Single test by name
php artisan test --compact --filter=notification_service_dispatch_fires_notification_created_event
```

---

## Key Files

| File | What to look at |
|---|---|
| `app/Services/NotificationService.php` | `dispatch()` method — must call `NotificationCreated::dispatch()` |
| `app/Events/NotificationCreated.php` | Channel name, `broadcastAs()` return value |
| `app/Livewire/NotificationBell.php` | `#[On()]` attribute must match channel+event |
| `routes/channels.php` | Channel auth closure |
| `app/Models/NotificationPreference.php` | `isAboveMinLevel()`, `LEVEL_ORDER` const |
| `app/Providers/AppServiceProvider.php` | `Event::listen()` registrations |
| `tests/Feature/NotificationSystemTest.php` | 18 system tests |
| `tests/Feature/NotificationBellComponentTest.php` | 9 Livewire component tests |
