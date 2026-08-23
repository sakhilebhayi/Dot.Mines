---
paths:
  - 'app/Services/Webhooks/**'
  - app/Jobs/DeliverWebhookJob.php
  - app/Listeners/WebhookEventSubscriber.php
  - app/Models/WebhookEndpoint.php
  - app/Models/WebhookDelivery.php
---

# Webhooks

## A user-supplied URL is an SSRF primitive — WebhookUrlGuard runs at save AND at send
The server POSTs to a URL the customer names, from inside the network. WebhookUrlGuard refuses non-https (in production), URLs carrying credentials, and any host where ANY resolved address is private/loopback/link-local/reserved (169.254.169.254 included). It runs twice on purpose: at save time, and again inside DeliverWebhookJob before every attempt, because DNS can be repointed after the endpoint is created. The HTTP call sets allow_redirects => false — a 302 to an internal host would walk straight around the guard. DNS lives behind HostResolver so tests bind a stub and assert the rules without touching the network; never inline gethostbyname into the guard. Frozen by tests/Feature/Webhooks/WebhookUrlGuardTest.

## Retries are self-dispatched, because the worker runs --tries=1
There is no resident queue worker (shared hosting): routes/console.php drains the DB queue from a once-a-minute cron tick with --tries=1. So DeliverWebhookJob NEVER throws — an escaping exception would fail a delivery permanently on the first connection blip. A failed attempt records the error and dispatches its own successor with a delay (WebhookDelivery::RETRY_DELAYS, 5 attempts over ~2.5h). Any new queue name MUST be added to that scheduler's --queue= list or its jobs pile up unserviced forever; 'webhooks' is in it.

## Only advertise events the app actually dispatches
WebhookEvent::SOURCES/CATALOGUE are the catalogue, and every entry must have a real dispatch site (ComplianceViolationDetected has a broadcast channel but nothing dispatches it, so it is deliberately excluded). A catalogue entry that never fires is worse than a missing one: the integrator builds a handler, sees nothing, and cannot tell whose fault it is. WebhookDeliveryTest asserts every advertised event has a dispatch site outside app/Events. Payloads reuse the API Resources (via WebhookEvent::fields()) so a webhook object matches the REST object field for field; dates go through ApiPayload::iso() so both surfaces emit the same format.

## Tenancy is explicit, not scoped
WebhookDispatcher reads endpoints with withoutTeamFilter() and filters team_id by hand, because it runs in queued jobs where the global scope has no authenticated user to filter on. That explicit where() is the only thing keeping one team's events out of another team's endpoint — do not "simplify" it away. If an event's team cannot be resolved the event is DROPPED, never guessed.

## The secret is shown once
Created on store(), returned in that response only, encrypted at rest, and in $hidden. WebhookEndpointResource has no secret field at all — a field that is sometimes redacted is how secrets reach logs.
