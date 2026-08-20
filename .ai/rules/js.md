---
paths:
  - 'resources/js/**'
---

# Js

## Never import alpinejs or assign window.Alpine — Livewire owns Alpine
Livewire v3's dist has bare `Alpine.…` references (e.g. `Alpine.reactive()` in its Component constructor) that resolve to the global window.Alpine at call time. Assigning a second Alpine to window.Alpine (as app.js once did) puts component data and DOM x-data scopes on two different reactivity engines, silently killing every server->client entangle() sync — all Jetstream confirms-password modals (2FA Enable, delete account) stopped opening. Livewire's auto-injected script sets window.Alpine itself; use that instance. Guarded by tests/Feature/SingleAlpineInstanceTest.php. Never suppress the "Detected multiple instances of Alpine" warning (the old __fromLivewire hack).
