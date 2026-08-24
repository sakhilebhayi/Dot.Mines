---
paths:
  - 'resources/views/**'
---

# Views

## One overlay contract for every modal backdrop
Full-screen modal overlays must use exactly: `<div data-app-overlay class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4 overflow-y-auto">`. Stacking scale: mobile-nav backdrop 45 < Leaflet panes/controls ≤1000 < modals 1100 (Jetstream x-modal included) < toasts 1200 — never invent new z layers. No bg-black/50, no backdrop blur on overlays. `data-app-overlay` drives the body scroll lock in app.js, and app.css gives overlays `align-items: safe center` so tall panels stay scroll-reachable on small screens. Enforced by tests/Feature/OverlayContractTest.php (static Blade scan) — sanctioned exceptions live in its allowlist.

## Modals: two sanctioned systems, one presentation
data-app-overlay (hand-rolled, Blade @if) and Jetstream x-modal (Alpine x-show) are the only modal flavours; OverlayContractTest freezes the backdrop recipe (single bg-black/60, z-[1100]). Both now CENTRE their panel (x-modal got a flex min-h-full items-center wrapper — it used to top-align, which read as a different design). app.js closes the mobile drawer whenever an overlay appears: drawer backdrop (z-45) under a modal backdrop was the one real double-dim path. setMobileNavOpen is idempotent — the overlay MutationObserver calls close() on every DOM mutation while a modal is open, so an unguarded setter dispatches a mobile-nav-changed event storm. Entangling a modal's `show` with a NON-boolean Livewire property is a trap: backdrop/Escape writes `false`, which Livewire coerces (false -> 0 on ?int), leaving half-closed server state — normalise falsy to null in an updatedX() hook (see Fleet::updatedAssignOperatorMachineId).
