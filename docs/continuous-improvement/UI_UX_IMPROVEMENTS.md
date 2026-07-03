# UI/UX Improvements

> Track user experience, accessibility, design consistency, and navigation improvements.

---

## Current UX Score: 76/100

---

## Open Issues

### UX-001 — Mobile Responsiveness Not Fully Tested
- **Pages Affected**: Fleet page, Live Map, Production Dashboard, Maintenance Dashboard
- **Finding**: These pages use complex data tables and map components not tested at 375px.
- **Fix**: Run each page through Chrome DevTools mobile emulation at 375px, 768px, 1024px.
- **Effort**: 3 days
- **Status**: 🔴 Open

### UX-002 — No Formal Accessibility Audit (WCAG 2.1 AA)
- **Finding**: No `aria-label`, `aria-describedby`, or role attributes audited. Icon-only buttons may be inaccessible to screen readers.
- **Fix**: Run `axe-core` on all pages; fix all critical + serious violations.
- **Effort**: 3 days
- **Status**: 🔴 Open

### UX-003 — Inconsistent Empty States
- **Finding**: Some components show a blank container when there is no data; others show a helpful empty state message.
- **Standard**: Every list/data component should show: icon + heading + description + action (when applicable).
- **Affected**: Reports, Analytics, some Dashboard sections.
- **Effort**: 2 days
- **Status**: 🟡 Planned

### UX-004 — Error States Not Always Shown
- **Finding**: When Livewire actions fail (network error, validation), some components fail silently.
- **Fix**: Ensure all `wire:click` actions that call APIs dispatch a `notify` event on failure.
- **Effort**: 2 days
- **Status**: 🟡 Planned

### UX-005 — Live Map Loads Slowly on First Render
- **Finding**: Leaflet map initializes after DOMContentLoaded; there is a brief blank map container.
- **Fix**: Add a skeleton loader or pulsing gradient while the map initializes.
- **Effort**: 1 day
- **Status**: 🔵 Backlog

### UX-006 — No Keyboard Shortcut Navigation
- **Finding**: Power users (dispatchers) cannot navigate between modules without mouse.
- **Fix**: Add `?` help overlay with common keyboard shortcuts; implement `g→f` (go to fleet), `g→m` (go to map), etc.
- **Effort**: 2 days
- **Status**: 🔵 Backlog

---

## Design System

### Colour Tokens (current)
| Intent | Light | Dark |
|---|---|---|
| Primary action | `bg-blue-600` | `dark:bg-blue-500` |
| Danger / delete | `bg-red-600` | `dark:bg-red-500` |
| Success | `bg-green-600` | `dark:bg-green-400` |
| Warning | `bg-amber-500` | `dark:bg-amber-400` |
| Engine running | `bg-emerald-500` (pulse) | — |
| Offline | `bg-red-500` | — |

### Status Badge Standards
| Status | Badge colour |
|---|---|
| Active / Working | Green |
| Idle / Parked | Blue |
| Maintenance | Orange |
| Offline | Red |

### Typography
- Page headings: `text-3xl font-bold`
- Section headings: `text-xl font-bold` / `text-lg font-semibold`
- Body: `text-sm` / `text-base`
- Meta / captions: `text-xs text-gray-500`
