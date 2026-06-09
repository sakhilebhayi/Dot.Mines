---
name: reverb-websocket-patterns
description: >
  Mines platform Laravel Reverb and WebSocket patterns. Use when: configuring or debugging Reverb,
  setting up Laravel Echo on the frontend, defining channel authorization in routes/channels.php,
  broadcasting events to private or presence channels, handling WebSocket reconnect scenarios,
  debugging why real-time updates are not reaching the browser, or working with
  RealtimeEventScheduler.
argument-hint: 'Describe the real-time or WebSocket task you need help with'
esm-layer: operational
esm-feeds-to:
  - notification-system
  - live-map-patterns
  - alert-system
  - community-feed-patterns
esm-consumes-from:
  - rbac-patterns
---

# Reverb WebSocket Patterns

## When to Use

- Configuring Laravel Reverb server settings
- Debugging real-time updates not arriving in the browser
- Defining channel authorization in `routes/channels.php`
- Broadcasting events to private or presence channels from server-side code
- Setting up or debugging Laravel Echo on the frontend
- Understanding RealtimeEventScheduler for periodic broadcasts
- Writing tests for WebSocket channel authorization
- Handling reconnect and fallback for flaky connections

---

## Architecture

```
Server event fires (PHP)
       ↓
Event::dispatch() with implements ShouldBroadcast
       ↓
Laravel Broadcasting → Reverb WebSocket server
       ↓
Echo client (browser) listening on channel
       ↓
Livewire #[On('echo-...')] or Alpine.js window.Echo.listen()
```

---

## Channel Types Used in This Platform

```
Private channels  — team or user scoped (requires auth)
  private-team.{teamId}.map              — GPS location updates
  private-team.{teamId}.notifications    — notification bell
  private-team.{teamId}.alerts           — real-time alert badge
  private-team.{teamId}.dispatch         — dispatch updates
  private-team.{teamId}.feed             — feed post creation
  private-user.{userId}                  — personal notifications

Presence channels  — NOT currently used (reserved for live collaboration)
```

---

## Channel Authorization (routes/channels.php)

```php
// Private team channel — any team member can subscribe
Broadcast::channel('team.{teamId}.notifications', function (User $user, int $teamId): bool {
    return $user->belongsToTeam(Team::find($teamId));
});

// Private user channel — only the user themselves
Broadcast::channel('user.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});
```

**CRITICAL:** All private channels **must** have an authorization callback. Missing auth = security vulnerability.

---

## Broadcasting an Event

```php
// In the Event class
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MachineLocationUpdated implements ShouldBroadcast
{
    public function __construct(public readonly Machine $machine) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("team.{$this->machine->team_id}.map"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'machine.location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'machine_id'  => $this->machine->id,
            'lat'         => $this->machine->latitude,
            'lng'         => $this->machine->longitude,
            'speed'       => $this->machine->speed,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
```

---

## Livewire Echo Listener Pattern

```php
// In any Livewire component
use Livewire\Attributes\On;

#[On('echo-private:team.{teamId}.map,machine.location.updated')]
public function onLocationUpdate(array $data): void
{
    // Find the machine in local state and update position
    $this->machines = $this->machines->map(function ($m) use ($data) {
        if ($m['id'] === $data['machine_id']) {
            $m['lat'] = $data['lat'];
            $m['lng'] = $data['lng'];
        }
        return $m;
    });
}
```

---

## Frontend Echo Setup (resources/js/echo.js)

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Required `.env` variables:
```
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## RealtimeEventScheduler

```php
// app/Services/RealtimeEventScheduler.php
// Periodically broadcasts aggregate state to all connected team clients
// Used for: fleet snapshot every 30s, alert count every 10s

// Runs via scheduler:
Schedule::call(fn() => app(RealtimeEventScheduler::class)->broadcastFleetSnapshot())
    ->everyThirtySeconds();
```

---

## Debugging Checklist

**Step 1 — Is Reverb running?**
```bash
ps aux | grep "reverb" | grep -v grep
# If not: php artisan reverb:start
```

**Step 2 — Is frontend built with current Echo config?**
```bash
# If VITE_ vars changed or resources/js/echo.js changed:
npm run build
# Or tell user to run: npm run dev
```

**Step 3 — Is the channel authorized?**
```bash
grep -n "Broadcast::channel" routes/channels.php
# Confirm the channel name matches the event's broadcastOn()
```

**Step 4 — Is the event actually broadcasting?**
```bash
php artisan tinker --execute '
// Manually fire a test broadcast
event(new App\Events\NotificationCreated(App\Models\Notification::first()));
echo "Dispatched";
'
# Then check browser Network tab → WS frame for the payload
```

**Step 5 — Check Reverb server log**
```bash
tail -f storage/logs/laravel.log | grep -i "reverb\|broadcast"
```

---

## Commands Reference

```bash
# Start Reverb server
php artisan reverb:start

# Start Reverb with debug output
php artisan reverb:start --debug

# Run WebSocket tests (channel auth)
php artisan test --compact tests/Feature/BroadcastTest.php
```
