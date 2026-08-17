# Dot Real-Time Standard

**Reference implementation:** Dot.Mines. This document codifies what was actually built there (Laravel Reverb, over four sub-projects) as the pattern every future Dot platform should follow for real-time/WebSocket infrastructure. It is not a generic Reverb tutorial — it's specifically what this build did, the bugs it found along the way, and why, so the next platform copies the architecture instead of rediscovering the same mistakes.

## 1. Architecture

```
Dot Platform
  → Laravel Event (implements ShouldBroadcast)
  → Broadcasting Layer (config/broadcasting.php)
  → Laravel Reverb (config/reverb.php, reverb:start process)
  → WebSocket Connection (Pusher protocol)
  → Authenticated Channel (BroadcastServiceProvider)
  → Frontend Listener (Echo, ReverbService.js)
  → UI Update
```

Each platform runs its own `reverb:start` process with its own credentials. There is no shared/central Reverb server across the ecosystem — cross-platform communication (§23 of the original master objective) is a separate, later concern layered on top of each platform's own real-time infrastructure, not a replacement for it.

## 2. Credentials

Generate unique, random credentials per environment (local/staging/production) and per platform. Never reuse a value across environments or platforms.

```bash
php -r 'echo random_int(100000, 999999);'    # REVERB_APP_ID
php -r 'echo bin2hex(random_bytes(20));'      # REVERB_APP_KEY
php -r 'echo bin2hex(random_bytes(32));'      # REVERB_APP_SECRET
```

Only `REVERB_APP_KEY` (via `VITE_REVERB_APP_KEY`) ever reaches the browser. `REVERB_APP_SECRET` stays server-side.

## 3. Environment variables — internal vs. public split

Two distinct address pairs, easy to conflate into one and get production wrong (this build did, initially):

| Variable | Meaning | Where it's used |
|---|---|---|
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | **Public** — what browsers connect to (`wss://your-domain.com`) | `config/reverb.php`'s `apps.apps[0].options` (signing/reporting), `VITE_REVERB_*` for Echo |
| `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | **Internal** — what the `reverb:start` process actually binds to. Loopback-only (`127.0.0.1`) in production, behind a reverse proxy | `config/reverb.php`'s `servers.reverb`, and `config/broadcasting.php`'s `reverb` connection |

The PHP backend's own event-publishing calls (`config/broadcasting.php`'s `reverb` connection, used by the queue worker to `POST /apps/{id}/events`) must point at the **internal** address, not the public one. Pointing it at the public domain sends every server-to-server broadcast out through the reverse proxy and back for no reason, and requires exposing Reverb's server-to-server API (`/apps/*`) publicly — which should never be necessary. Only Reverb's client path (`/app/{key}`) needs to be reverse-proxied.

Local development doesn't need this split — no reverse proxy exists there, so `REVERB_SERVER_HOST`/`PORT` can be left unset and both configs fall back to the public values.

## 4. Config file correctness

`config/reverb.php` must match the schema the installed `laravel/reverb` version actually reads (`servers.<name>.*`, `apps.provider`/`apps.apps[]`) — copy it from `vendor/laravel/reverb/config/reverb.php`, don't hand-roll it. A config file with plausible-looking keys that don't match what the package reads will silently fall back to defaults everywhere, and `reverb:start` will boot without ever surfacing an error — this was the actual root cause of Reverb "not working" in Dot.Mines before this rebuild.

## 5. Broadcasting event conventions

- Every real-time event implements `ShouldBroadcast` (queued, not `ShouldBroadcastNow`, unless the event is truly latency-critical and the queue is known to be fast — see §8).
- Override `broadcastAs()` with a short, dotted, lowercase name (`machine.location.updated`, not the FQCN default).
- **Critical**: the frontend must listen with a leading dot — `channel.listen('.machine.location.updated', cb)`, not `channel.listen('MachineLocationUpdated', cb)`. Laravel Echo's default `EventFormatter` namespaces a bare (no-leading-dot) name to `App.Events.<name>` and listens for that, which never matches an overridden short `broadcastAs()` name. This exact mismatch meant every broadcast event in Dot.Mines was silently unreceived by the frontend for a full sub-project before it was caught — the channels were authorized, the events were being broadcast, but nothing was listening for the right name. If a "the backend broadcasts it but nothing happens client-side" bug ever appears, check this first.
- Channel naming: `{resource}.{id}` for a single resource (`machine.{id}`), `{resource}.team.{teamId}` or `team.{teamId}.{resource}` for team-scoped feeds — Dot.Mines has both forms today (historical, not a deliberate choice) and either is fine as long as it's a `PrivateChannel`, not public.
- Keep `broadcastWith()` payloads minimal: only what the UI update actually needs. No PII, no financial data, no fields the receiving user isn't otherwise authorized to see.

## 6. Channel authorization: membership AND permission

Every private channel needs a `Broadcast::channel()` callback in a `BroadcastServiceProvider` (registered in `bootstrap/providers.php` — easy to write the callback and forget the registration, which is exactly what happened here; the callback silently never runs). Team membership alone is **not sufficient** authorization if the platform has any role/permission system — check both:

```php
Broadcast::channel('team.{teamId}', function (User $user, $teamId) {
    if (! belongsToTeamId($user, $teamId)) return false;
    if (! $user->hasPermission('track_machines')) return false;
    return ['id' => $user->id, 'name' => $user->name];
});
```

Match the permission to whatever the equivalent HTTP `Policy` already requires for the same resource — a WebSocket channel is a second delivery path for the same data, not a separate authorization surface with its own, looser rules. In Dot.Mines, `MachinePolicy::trackLocation()` required `track_machines` on the HTTP side while the WebSocket channel only checked team membership for months; a `viewer` role (deliberately granted `view_machines` but not `track_machines`) was receiving live GPS pushes the HTTP layer would have refused it.

**Fail closed on malformed identifiers.** Route-segment placeholders like `{teamId}` match any non-slash text, not just digits. A typed `int $teamId` closure parameter throws an uncaught `TypeError` on a non-numeric value (500, not 403); passing an unvalidated string straight into an Eloquent `find()` on an integer column can throw at the database layer too. Validate and convert explicitly (see `BroadcastServiceProvider::toId()`) before ever touching the database or a typed parameter.

If Jetstream teams are in use: `User::belongsToTeam()` expects a `Team` **model**, not a raw id — passing an id directly silently evaluates to `false` on every call via a PHP warning, not an exception. This is easy to miss because it fails safe (denies access) rather than loudly, so it can sit unnoticed for a long time. Resolve the model first.

## 7. Frontend wiring

- `Echo.private(channel)` for private channels — not `Echo.channel(channel)` (public). The two silently coexist without erroring, so this is another "authorization is correct, nothing is received" class of bug.
- Initialize subscriptions from a component that's mounted on every authenticated page (e.g. the navbar), not a single feature page — otherwise real-time updates for e.g. the notification bell only work while a user happens to be on the one page that subscribes.
- Frontend Echo auth: standard session + CSRF header (`X-CSRF-TOKEN` against `/broadcasting/auth`), not a bearer token — there's no bearer-token auth path for browser-session users here.

## 8. Queues

Broadcast events should be queued (`ShouldBroadcast`, not `ShouldBroadcastNow`) so a slow or unavailable Reverb process never blocks the HTTP request that triggered the event. Production queue connection: Redis (`QUEUE_CONNECTION=redis`) — also required for Reverb's own horizontal-scaling option (`REVERB_SCALING_ENABLED`, Redis pub/sub across multiple `reverb:start` processes), and matches the process-supervision files below.

## 9. Reconnection and reconciliation

WebSockets are not the source of truth; the database is. A client that's disconnected simply misses whatever broadcast during that window — there is no replay.

- Monitor the underlying connection's own state (`Echo.connector.pusher.connection`) rather than reimplementing reconnect logic — pusher-js (which Reverb speaks) already retries automatically.
- Surface connection state to the user (connected/connecting/reconnecting/disconnected) so they're never left assuming they have live data when they don't.
- On reconnect after a real drop, re-fetch from the backend (not from the socket) whatever the UI was showing, rather than trusting that nothing was missed.

## 10. Health check

Laravel's built-in `/up` only proves the app boots — it says nothing about broadcasting config, queue reachability, or whether `reverb:start` is actually running. Add a dedicated real-time health check (Dot.Mines: `GET /up/realtime`) that walks each link independently and reports which one is broken:

1. Broadcasting config resolves to the real-time driver with credentials present
2. Queue connection reachable
3. The `reverb:start` process is actually accepting connections (raw socket probe against the *internal* host/port, bypassing the reverse proxy)

Channel authorization and frontend delivery aren't practical to check from a server-side health endpoint — those need a real authenticated client. Cover authorization with feature tests (§12) and frontend delivery with manual browser verification.

## 11. Production infrastructure

- `reverb:start` needs the same process supervision as a queue worker (systemd unit or Supervisor program) — it's a long-running process, not a request/response action. It had none in Dot.Mines until this was built.
- TLS terminates at the reverse proxy (Nginx/Apache), not at Reverb itself — Reverb binds plain HTTP internally; the proxy is what turns that into `wss://` for browsers. This needs an explicit `Upgrade`/`Connection: upgrade` proxy block for Reverb's client path only (`/app/{key}`) — a generic reverse-proxy config for a Laravel app has no reason to already have this, and won't work for WebSockets without it.
- Bind the internal Reverb port to loopback only (`REVERB_SERVER_HOST=127.0.0.1`), not `0.0.0.0` — only the reverse proxy on the same box needs to reach it.

## 12. Testing

- Feature-test channel authorization by posting to the real `/broadcasting/auth` endpoint, not by unit-testing the closures in isolation — this exercises the actual route, middleware, and driver resolution, and is what caught every bug listed in §5–6 above.
- The test environment's broadcasting driver is typically `null` (so other tests don't attempt real network calls) — the `null` driver's `auth()` is a no-op that authorizes everything unconditionally. Channel-authorization tests must explicitly switch to a real Pusher-protocol driver (`config(['broadcasting.default' => 'reverb'])`) **and re-run the provider's channel registration** afterward — `Broadcast::channel()` registers against whichever driver instance is current at call time, and the provider already booted against the `null` driver during app bootstrap before any test's `setUp()` runs.
- Cover, per channel: authorized member succeeds, non-member fails, member without the required permission fails, cross-tenant access fails, unauthenticated fails, and a malformed identifier fails closed (not with a 500).

## 13. What's deliberately not built (yet)

- A cross-platform ecosystem event bus (master objective §23) — each platform's own real-time layer should be solid first; that's a separate, later piece of work once more than one platform is on this standard.
- Application-level metrics/dashboards for connection counts, throughput, etc. — Reverb's own process logs (redirected to a file by the supervisor config) cover this for now; a real metrics pipeline is new infrastructure, not a hardening of what's here.
- Event-replay/offset-based reconciliation — reconciliation here means "re-fetch current state from the database," not "replay the exact events that were missed." The latter needs server-side event logging this doesn't have.
