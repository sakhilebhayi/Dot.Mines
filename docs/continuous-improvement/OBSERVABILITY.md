# Observability Improvements

> Track logging, monitoring, tracing, and alerting enhancements.

---

## Current Observability Score: 65/100

---

## Critical Gaps

### OBS-001 — No Health Check Endpoint
- **Risk**: Critical — load balancers, container orchestrators, and uptime monitors cannot verify app health
- **Fix**: Add `GET /health` returning:
  ```json
  {
    "status": "ok",
    "checks": {
      "database": "ok",
      "cache": "ok",
      "queue": "ok"
    },
    "timestamp": "2026-07-02T12:00:00Z"
  }
  ```
- **Package**: Consider `laravel/health`
- **Effort**: 1 day
- **Status**: 🔴 Open

### OBS-002 — No External Uptime Monitoring
- **Risk**: Critical — downtime goes undetected until a user reports it
- **Fix**: Add Better Uptime, UptimeRobot, or Pingdom monitoring `https://mines.infodot.co.za/health`
- **Effort**: 0.5 days
- **Status**: 🔴 Open

### OBS-003 — Sentry DSN Not Configured
- **Risk**: High — `SENTRY_DSN=` is blank; all unhandled exceptions are only logged to DB, not alerted
- **Fix**: Configure Sentry project; add DSN to `.env`; verify `sentry.php` config
- **Effort**: 0.5 days
- **Status**: 🔴 Open

---

## Logging

### OBS-004 — Unstructured Log Messages
- **Finding**: `Log::info('SyncBellLocationsJob completed', $result)` — context is structured but many log lines are plain strings
- **Fix**: Enforce structured JSON logs using `LOG_CHANNEL=stack` with a JSON formatter in production
- **Standard**:
  ```php
  Log::info('bell.sync.completed', [
      'job' => 'SyncBellLocationsJob',
      'machine_count' => 12,
      'inserted' => 45,
      'duration_ms' => 1230,
  ]);
  ```
- **Status**: 🟡 Planned

### OBS-005 — No Request Correlation IDs
- **Finding**: No trace ID is attached to requests/jobs, making it impossible to correlate a user action with background job logs
- **Fix**: Add `X-Request-ID` middleware that sets a UUID on every request and attaches it to all log context
- **Effort**: 1 day
- **Status**: 🔵 Backlog

---

## Metrics

### OBS-006 — Laravel Pulse Not Fully Configured
- **Finding**: `PULSE_ENABLED=true` but Pulse is not actively monitored or alerted on
- **Fix**:
  - Enable Pulse DB, Queue, Cache, HTTP recorders
  - Set up Pulse dashboard access for engineering team
  - Add slow query and memory spike alerts
- **Effort**: 1 day
- **Status**: 🟡 Planned

---

## Queue Monitoring

### OBS-007 — No Horizon Alerts on Stalled Jobs
- **Finding**: Horizon dashboard is available but no alerts are configured
- **Fix**: Add Horizon `pause` + `continue` webhook alerts when queue backlog > 100 jobs for > 5 minutes
- **Effort**: 1 day
- **Status**: 🟡 Planned

---

## Database Monitoring

### OBS-008 — No Slow Query Detection
- **Fix**:
  ```php
  // AppServiceProvider::boot()
  DB::listen(function ($query) {
      if ($query->time > 500) {
          Log::warning('slow.query', [
              'sql' => $query->sql,
              'bindings' => $query->bindings,
              'time_ms' => $query->time,
          ]);
      }
  });
  ```
- **Status**: 🔴 Open

---

## Monitoring Stack Recommendation

| Layer | Tool | Status |
|---|---|---|
| Error tracking | Sentry | ⚠️ DSN not configured |
| APM / Tracing | Sentry Tracing or OpenTelemetry | ❌ Not started |
| Uptime | Better Uptime | ❌ Not started |
| Application metrics | Laravel Pulse | ⚠️ Enabled but not monitored |
| Queue health | Laravel Horizon | ✅ Running |
| Log aggregation | CloudWatch / Loki | ❌ Not started |
| Database monitoring | Pulse + slow query log | ⚠️ Not configured |
