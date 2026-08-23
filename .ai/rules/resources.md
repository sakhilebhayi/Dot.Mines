---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## API payloads are shaped by Resources — never return a raw Eloquent model
Every API payload goes through an App\Http\Resources class: lists via ApiResponse::paginated($p, XResource::class), single models via XResource::make($model). Returning a model raw makes the DB schema the public contract and leaks internals — it previously shipped sync_version/allocation_state/integration_id, report file_path (a storage location), Integration credentials (encrypted:json that DECRYPTS on read), and whole User objects (email, 2FA timestamp, notification_preferences) through eager-loaded relations. Related users use UserSummaryResource (id+name only). Do NOT guard with a partial ->select() — that is a remembered guard, it silently omits fields the resource exposes, and it caused a 500 when timestamps were absent; query fully and let the resource whitelist. Use whenLoaded() for relations and the FormatsTimestamps::iso() helper for loosely-typed date columns. Frozen by tests/Feature/Api/ResourceFieldExposureTest.
