---
paths:
  - 'app/**'
---

# App

## Actions pattern for write operations
Business write operations live in `app/Actions/{Domain}/` — one class, one operation, a typed `execute()` with explicit input/output. Controllers and Livewire components orchestrate (validate → authorize → call Action → respond); Actions own the business rules; Services own external integrations and cross-cutting infrastructure. Do not add repositories over Eloquent or interfaces with a single implementation. Introduced by the 2026-08-22 refactor program (see docs/superpowers/specs/2026-08-22-codebase-refactor-ai-readiness-design.md); adopted progressively — follow it for all new write paths and whenever touching an old one.
