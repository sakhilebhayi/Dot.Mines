---
paths:
  - app/Services/OpenApiGenerator.php
---

# Services

## API docs are generated from the routes — never hand-write an endpoint list
OpenApiGenerator builds the spec by reflecting the route table: paths/verbs/path-params from routes, summaries from controller action docblocks (first line; "GET /api/x" and "Query params:" lines are stripped), query/body params parsed from the action's $request->validate([...]) rules including in: enums and required, and the token permission from EnsureTokenAbility::abilitiesFor() so docs and enforcement cannot disagree. Served publicly at /api/openapi.json (a schema, not data) and rendered server-side in the docs page via reference() — no spec package, no CDN viewer, because the app adds no dependencies and loads no CDN scripts. Cached in production only; caching in dev would serve a stale reference. Never cache it on a TTL alone: a fixed key with an hour TTL meant a deploy that renamed query params kept publishing the old spec until the TTL expired, and a generated doc that silently lags its own source is worse than a hand-written one. cacheKey() fingerprints the route table plus every reflected controller's mtime, so a deploy lands on a new key and regenerates — the controller mtime is the load-bearing half, because that bug had identical routes and only the validate() rules moved. Do not "fix" staleness with a cache:forget step in the deploy script; that is a second place to keep in sync and it fails silently. Frozen by tests/Feature/Api/OpenApiCacheKeyTest. Give new controller actions a one-line docblock summary and real validate() rules and they document themselves. Frozen by tests/Feature/Api/OpenApiSpecTest (every route must appear; permissions must match the middleware; operationIds unique).
