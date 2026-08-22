// Livewire v3 bundles and starts its own Alpine, and its dist assigns it to
// `window.Alpine`. NEVER import/assign a second Alpine here: Livewire's dist
// contains bare `Alpine.…` references (e.g. `Alpine.reactive()` in the
// Component constructor) that resolve to the GLOBAL at call time, so
// overwriting `window.Alpine` splits the page across two reactivity engines
// -- component data lands on one engine while the DOM's x-data scopes run on
// the other, which silently breaks every server->client `entangle()` sync
// (confirmed live: all Jetstream confirms-password modals, incl. the 2FA
// Enable flow, never opened because `x-show="show"` never received the
// entangled update).

// Shared mobile-nav drawer state. The sidebar, navbar, and backdrop are
// three separate Livewire/Blade roots, so a plain local `x-data` property on
// one isn't visible to the others.
//
// Plain window CustomEvents instead of Alpine.store(): they don't depend on
// Alpine at all, so nav state can't be caught in any Alpine boot-order or
// instance issue. Each Blade root keeps its own local `mobileOpen` and stays
// in sync by listening for 'mobile-nav-changed'.
let mobileNavOpen = false;

function setMobileNavOpen(open) {
	mobileNavOpen = open;
	document.body.classList.toggle('overflow-hidden', open);
	window.dispatchEvent(new CustomEvent('mobile-nav-changed', { detail: { open } }));
}

window.mobileNav = {
	toggle: () => setMobileNavOpen(!mobileNavOpen),
	close: () => setMobileNavOpen(false),
};

window.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && mobileNavOpen) {
		setMobileNavOpen(false);
	}
});
import './bootstrap';
import './animations';  // Enhanced UX/UI animations
import './livewire-realtime';
import ReverbService from './services/ReverbService.js';
import LivewireEcho from './utils/LivewireEcho.js';
import RealtimeMapManager from './services/RealtimeMapManager.js';
import ToastNotificationService from './services/ToastNotificationService.js';
import GeofenceVisualizationManager from './services/GeofenceVisualizationManager.js';

// Import Leaflet locally (avoids CDN issues)
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-providers';

// Make Leaflet globally available for inline scripts
window.L = L;

// Make services globally available for Livewire components
window.ReverbService = ReverbService;
window.LivewireEcho = LivewireEcho;
window.RealtimeMapManager = RealtimeMapManager;
window.ToastNotificationService = ToastNotificationService;
window.GeofenceVisualizationManager = GeofenceVisualizationManager;

// Ensure Livewire-dispatched events are re-dispatched on `window` so
// Alpine listeners using `@notify.window` receive them reliably.
document.addEventListener('livewire:load', function () {
	if (window.Livewire && typeof window.Livewire.hook === 'function') {
		window.Livewire.hook('message.processed', (message, component) => {
			try {
				const dispatches = message?.response?.effects?.dispatches || [];
				dispatches.forEach(d => {
					const name = d.name;
					// Livewire sends params as an array; we take the first element as payload
					const payload = (d.params && d.params.length) ? d.params[0] : {};
					window.dispatchEvent(new CustomEvent(name, { detail: payload, bubbles: true, composed: true }));
				});
			} catch (e) {
				// swallow — non-critical
				console.error('Livewire dispatch re-dispatch failed', e);
			}
		});
	}
});

// Body scroll lock for standardised overlays (`data-app-overlay`). These
// modals mount/unmount via Blade @if, so DOM presence is the open signal.
// Jetstream modals lock their own scroll via x-trap.noscroll and are not
// marked. Padding compensates for the vanished scrollbar so the page
// underneath never shifts when the lock engages.
(function () {
	let locked = false;

	const syncScrollLock = () => {
		const open = document.querySelector('[data-app-overlay]') !== null;
		if (open === locked) return;
		locked = open;
		if (open) {
			const scrollbar = window.innerWidth - document.documentElement.clientWidth;
			document.body.style.overflow = 'hidden';
			if (scrollbar > 0) {
				document.body.style.paddingRight = `${scrollbar}px`;
			}
		} else {
			document.body.style.overflow = '';
			document.body.style.paddingRight = '';
		}
	};

	const observer = new MutationObserver(syncScrollLock);
	const start = () => {
		observer.observe(document.body, { childList: true, subtree: true });
		syncScrollLock();
	};
	if (document.body) {
		start();
	} else {
		document.addEventListener('DOMContentLoaded', start);
	}
})();
