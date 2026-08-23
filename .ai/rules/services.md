---
paths:
  - app/Services/OpenApiGenerator.php
---

# Services

## API docs are generated from the routes — never hand-write an endpoint list
OpenApiGenerator builds the spec by reflecting the route table: paths/verbs/path-params from routes, summaries from controller action docblocks (first line; "GET /api/x" and "Query params:" lines are stripped), query/body params parsed from the action's $request->validate([...]) rules including in: enums and required, and the token permission from EnsureTokenAbility::abilitiesFor() so docs and enforcement cannot disagree. Served publicly at /api/openapi.json (a schema, not data) and rendered server-side in the docs page via reference() — no spec package, no CDN viewer, because the app adds no dependencies and loads no CDN scripts. Cached in production only; caching in dev would serve a stale reference. Give new controller actions a one-line docblock summary and real validate() rules and they document themselves. Frozen by tests/Feature/Api/OpenApiSpecTest (every route must appear; permissions must match the middleware; operationIds unique).
