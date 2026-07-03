# API Improvements

> Track endpoint improvements, consistency gaps, and documentation progress.

---

## Current API Score: 75/100

---

## Open Issues

### API-001 — Inconsistent Error Response Format
- **Finding**: Some endpoints return HTML error pages; others return JSON without a consistent envelope.
- **Standard**:
  ```json
  {
    "success": false,
    "message": "Validation failed",
    "errors": { "field": ["error message"] },
    "error_ref": "uuid-for-support"
  }
  ```
- **Fix**: Update `app/Exceptions/Handler.php` `render()` method to always return JSON for API routes.
- **Status**: 🔴 Open

### API-002 — Missing Pagination on Collection Endpoints
- **Finding**: Several list endpoints return unbounded collections. Will break at scale.
- **Affected Endpoints** (audit in progress):
  - `GET /api/v1/machines` — needs cursor pagination
  - `GET /api/v1/alerts` — needs cursor pagination
  - `GET /api/v1/fuel-transactions` — needs cursor pagination
- **Fix**: Enforce `->cursorPaginate(50)` or `->paginate(25)` on all collection endpoints.
- **Status**: 🟡 Planned

### API-003 — No Idempotency Keys on Mutation Endpoints
- **Finding**: POST/PUT endpoints are not idempotent. Duplicate requests from retrying clients create duplicate records.
- **Affected**: Machine creation, fuel transaction creation, production record creation.
- **Fix**: Add `Idempotency-Key` header support; cache results for 24h keyed on the UUID.
- **Status**: 🔵 Backlog

### API-004 — Rate Limiting Inconsistent Across Routes
- **Finding**: Auth routes throttled (5/min); API routes use default 60/min globally but some endpoints lack specific limits.
- **Fix**: Apply tiered limits: `auth:5,1`, `read:120,1`, `write:30,1`, `sync:10,1`
- **Status**: 🟡 Planned

### API-005 — OpenAPI Documentation Incomplete
- **Finding**: Scramble is installed but many routes lack `#[ResponseFromApiResource]` / `#[Response]` annotations.
- **Fix**: Add Scramble annotations to all API controllers; generate spec in CI.
- **Effort**: 5 days
- **Status**: 🔵 Backlog

### API-006 — No API Changelog
- **Finding**: No record of breaking or additive API changes. Consumers cannot track changes.
- **Fix**: Create `docs/API_CHANGELOG.md`; add API change entry requirement to `RELEASE_CHECKLIST.md`.
- **Effort**: 0.5 days setup + ongoing
- **Status**: 🔵 Backlog

---

## API Standards Reference

### Response Envelope
```json
{
  "success": true,
  "data": {},
  "meta": { "page": 1, "per_page": 25, "total": 142 }
}
```

### Error Envelope
```json
{
  "success": false,
  "message": "Human-readable description",
  "errors": {},
  "error_ref": "550e8400-e29b-41d4-a716-446655440000"
}
```

### Pagination (cursor-based for large datasets)
```json
{
  "data": [...],
  "links": {
    "prev": null,
    "next": "https://api.mines.app/v1/machines?cursor=abc123"
  }
}
```

### Versioning
- Current version: `v1` (prefix: `/api/v1/`)
- Breaking changes require a new version (`v2`)
- Old versions supported for 12 months after new version release
