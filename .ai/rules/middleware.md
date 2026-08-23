---
paths:
  - app/Http/Middleware/NormalizeApiParameters.php
---

# Middleware

## API parameter vocabulary: start_date/end_date and bare filter names
One vocabulary across every endpoint: a time range is start_date/end_date (chosen because it was already the majority spelling — 7 sites vs 2), and a filter is the bare field name (status, type), never filter_*. Endpoints with a relative shorthand (hours_back, days) also accept start_date/end_date so "bound a time range" works identically everywhere; the shorthand applies only when start_date is absent. Legacy names (date_from, date_to, filter_status, filter_type) are translated by NormalizeApiParameters (alias 'api.params', registered on the api group BEFORE ensure_team) so existing integrations keep working — it runs before validation, so a legacy name still satisfies a `required` rule. Canonical wins if both are sent. Controllers and validation rules must use canonical names only; the generated docs read those rules, so an alias must never appear in a validate() block. Frozen by tests/Feature/Api/ParameterNamingTest. Retire an alias only after the deprecation is published and logs show nobody sending it.
