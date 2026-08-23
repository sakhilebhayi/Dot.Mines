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
window.RealtimeMapManager = RealtimeMapManager;
window.ToastNotificationService = ToastNotificationService;
window.GeofenceVisualizationManager = GeofenceVisualizationManager;

// (R7 dead-code pass) A Livewire v2-era block lived here that re-dispatched
// component events onto window from a 'livewire:load' listener via the
// 'message.processed' hook. Livewire v3 emits neither ('livewire:init' /
// v3 hook names replaced them), so the listener never fired -- and v3
// already dispatches component events as window CustomEvents natively,
// which is why @notify.window has been working all along.

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

// Hybrid local layer (Slice 2): service worker app shell + IndexedDB sync.
// The sync client only boots on pages that inject an authenticated
// __syncContext; the /offline shell instead renders the cached snapshot.
import * as syncClient from './local/syncClient';
import * as realtimeBridge from './local/realtimeBridge';
import * as offlineSnapshot from './local/offlineSnapshot';

if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker.register('/sw.js').catch(() => {
			// Registration failure just means no offline shell; never fatal.
		});
	});
}

document.addEventListener('DOMContentLoaded', () => {
	if (window.__syncContext) {
		syncClient.boot(window.__syncContext);
		realtimeBridge.boot(window.__syncContext);
	}

	const snapshotRoot = document.getElementById('offline-snapshot');
	if (snapshotRoot) {
		offlineSnapshot.render(snapshotRoot);
	}
});
