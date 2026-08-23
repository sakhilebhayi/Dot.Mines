---
paths:
  - routes/api.php
---

# Routes

## API token abilities are enforced by HTTP verb via the token.ability middleware
The api group runs EnsureTokenAbility (alias token.ability, in bootstrap/app.php after auth:sanctum). It maps verb→Sanctum ability using the create/read/update/delete vocabulary: GET/HEAD→read, POST→create OR update, PUT/PATCH→update, DELETE→delete. First-party/session requests (console, sync client, live-map) pass automatically because Sanctum's TransientToken.can() returns true — only personal access tokens carry finite abilities. New routes are covered automatically; do NOT rely on per-route ability middleware. Frozen by tests/Feature/Api/TokenAbilityEnforcementTest. In tests, Sanctum::actingAs($user) defaults to NO abilities (not ['*']) — pass ['*'] for a full-access token or the specific ability you're testing.
