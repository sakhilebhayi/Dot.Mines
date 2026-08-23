---
paths:
  - routes/api.php
  - routes/api_v1.php
---

# Routes

## Add API endpoints to routes/api_v1.php, never to routes/api.php
routes/api.php is the composition root: it decides which versions exist and where they are served. routes/api_v1.php holds the endpoints with no version in them, and api.php registers that ONE file TWICE — under `/api/v1` (name prefix api.v1., what the docs describe) and bare at `/api` (name prefix api.unversioned., what every pre-versioning integration already calls) — with the identical middleware stack. Defining endpoints once is what stops the two spellings drifting; add a route here and both spellings exist automatically. The name prefixes are what keep route:cache working (Laravel throws on duplicate route names, and prod caches routes).

## The bare /api/... paths are pinned to v1 FOREVER — they are not "latest"
ApiVersion::CURRENT is the documented version; ApiVersion::PINNED_FOR_UNVERSIONED is what bare paths resolve to. They are separate constants on purpose: when v2 ships, CURRENT moves and PINNED does NOT, so v2 is served only at /api/v2 and /api/machines keeps answering exactly as before. Making them track each other would break every client that never asked to upgrade, silently, on deploy day. Do not "deprecate" or redirect the bare paths either: a sunset date buys nothing and costs a future outage for whoever missed the memo, while keeping them costs one registration. New version = new route file (routes/api_v2.php) + a third registration; the bare one keeps pointing at api_v1.php. Frozen by tests/Feature/Api/ApiVersioningTest, which also asserts both spellings run the same action AND the same middleware — an alias registered without auth:sanctum/ensure_team would be an open door to another team's data.

## API token abilities are enforced by HTTP verb via the token.ability middleware
The api group runs EnsureTokenAbility (alias token.ability, in bootstrap/app.php after auth:sanctum). It maps verb→Sanctum ability using the create/read/update/delete vocabulary: GET/HEAD→read, POST→create OR update, PUT/PATCH→update, DELETE→delete. First-party/session requests (console, sync client, live-map) pass automatically because Sanctum's TransientToken.can() returns true — only personal access tokens carry finite abilities. New routes are covered automatically; do NOT rely on per-route ability middleware. Frozen by tests/Feature/Api/TokenAbilityEnforcementTest. In tests, Sanctum::actingAs($user) defaults to NO abilities (not ['*']) — pass ['*'] for a full-access token or the specific ability you're testing.
