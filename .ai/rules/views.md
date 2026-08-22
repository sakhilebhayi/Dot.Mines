---
paths:
  - 'resources/views/**'
---

# Views

## One overlay contract for every modal backdrop
Full-screen modal overlays must use exactly: `<div data-app-overlay class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4 overflow-y-auto">`. Stacking scale: mobile-nav backdrop 45 < Leaflet panes/controls ≤1000 < modals 1100 (Jetstream x-modal included) < toasts 1200 — never invent new z layers. No bg-black/50, no backdrop blur on overlays. `data-app-overlay` drives the body scroll lock in app.js, and app.css gives overlays `align-items: safe center` so tall panels stay scroll-reachable on small screens. Enforced by tests/Feature/OverlayContractTest.php (static Blade scan) — sanctioned exceptions live in its allowlist.
