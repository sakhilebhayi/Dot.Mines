---
paths:
  - 'app/Services/Feed/**'
  - app/Models/FeedItem.php
  - app/Livewire/OperationsFeed.php
  - app/Events/FeedItemPosted.php
---

# Mine Operations Feed

## The feed consumes; it never calculates
feed_items rows are published by the module that owns the data, via FeedPublisher — the page only filters and renders. Never add feed-side derivation of loads/cycles/status/fuel: numbers in a feed row are copied from the owning record at event time (e.g. geofence exit tonnage from the same geofence_entries row the Production page reads). occurred_at is when the underlying event HAPPENED (entry_time, triggered_at), not when the row was written — pass it explicitly from normalisers; the now() default exists only for human posts.

## Dedupe lives in the database, not in memory
dedupe_key is unique per (team_id, dedupe_key); FeedPublisher swallows UniqueConstraintViolationException — being delivered twice is normal, not exceptional, so no read-then-write check (that's a race). Key conventions: alert:{id}, geofence-entered/exited:{entry_id}, offline:{machine_id}:{last_seen_ts} (re-detecting one outage ≠ a new outage; coming back and dropping again IS), maintenance:{machine_id}:{predicted_date}, assignment:{id}, compliance:{notification dedupe key} — the feed and the notification share the compliance key so they can never disagree about whether a milestone fired.

## One set of domain events, three consumers
FeedEventNormaliser hangs off the SAME events as the live UI and outbound webhooks (AppServiceProvider). Add a new operational event once, and wire each consumer there — never invent feed-only hooks for things the platform already announces. Registration uses a closure (fn => app(Normaliser)->handle($event)) because psalm cannot trace [Class::class,'method'] callables.

## Telemetry stays out
No per-GPS-tick or per-metric-row items, ever. Raw telemetry belongs to the live map and sync layer; the feed gets discrete operational events and (future) aggregates computed by owning services.

## Medical never reaches the feed
Operator compliance items are published for licences and training only; medical expiry alerts stay notification-only (ComplianceAlertService skips kind=medical for the feed) because the feed's audience (every view_feed holder incl. viewer role) is wider than the medical permission.

## Real-time + CSP traps
FeedItemPosted broadcasts '.feed.item.posted' on the EXISTING private team.{id} channel (already authorised) carrying only {id, category, source} — the page refreshes through Livewire so reads stay authorised server-side. The blade wakes Livewire via Livewire.dispatch('feed-refresh') → #[On('feed-refresh')] (a raw '$refresh' dispatch is not a thing from window scope). wire:poll.60s is the dropped-websocket fallback — a bare wire:poll hits every 2.5s. ScanBladeUnescaped's regex matches literal "echo <ws>...;" case-INSENSITIVELY, so inline JS mentioning window.Echo must never have whitespace after the word Echo ("window.Echo &&" trips it; "window.Echo)" and "window.Echo.private" are safe).

## Permissions
view_feed (every role incl. viewer), post_feed (admin, fleet_manager), pin_feed (admin only). System items are undeletable by anyone; users delete their own posts, pin_feed holders any post. Pins expire via pinned_until (ceiling 2 weeks); expired pin = not pinned, no cleanup job needed.

Frozen by tests/Feature/Feed/.
