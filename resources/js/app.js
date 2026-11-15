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

// Navigation shell (R-52). Two pieces, neither owned by Alpine or Livewire:
//  1. The phone / tablet menu is the shared dot-ui header's menu button in "adopt" mode: dot-nav.js opens the sidebar
//     (#mines-sidebar, [data-dh-open]), draws the scrim on <body>, locks the page, traps focus and closes on Escape, a scrim
//     tap, the close button and a link tap. window.mobileNav stays as a thin shim for the overlay code below, which closes
//     the menu when a modal opens.
//  2. The desktop icon rail: html[data-rail] (set before first paint by the head script in layouts/_dot-ui-head.blade.php,
//     remembered in localStorage under the same key as before), toggled by [data-rail-toggle].
window.mobileNav = {
	toggle: () => {},
	close: () => window.DotNav?.closeDrawer?.(),
};

function syncRailToggle() {
	const rail = document.documentElement.getAttribute('data-rail') === 'true';
	document.querySelectorAll('[data-rail-toggle]').forEach((button) => {
		button.setAttribute('aria-pressed', rail ? 'true' : 'false');
		button.setAttribute('aria-label', rail ? 'Expand the sidebar' : 'Collapse the sidebar');
	});
}

document.addEventListener('click', (event) => {
	const button = event.target instanceof Element ? event.target.closest('[data-rail-toggle]') : null;
	if (!button) return;
	const rail = document.documentElement.getAttribute('data-rail') !== 'true';
	document.documentElement.setAttribute('data-rail', rail ? 'true' : 'false');
	try {
		localStorage.setItem('sidebarOpen', rail ? 'false' : 'true');
	} catch (e) {
		// storage blocked: the choice simply does not persist
	}
	syncRailToggle();
});

document.addEventListener('DOMContentLoaded', syncRailToggle);
document.addEventListener('livewire:navigated', syncRailToggle);

// dot-ui behaviour (search pill, results keyboard model, switcher, header: window.DotNav), bundled and minified with the app
// instead of four separate requests. The files stay hash-pinned in public/dot-ui; order matters (the header script reads the others).
import '../../public/dot-ui/js/dot-search.js';
import '../../public/dot-ui/js/dot-search-results.js';
import '../../public/dot-ui/js/dot-switcher.js';
import '../../public/dot-ui/js/dot-nav.js';
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

		// A modal over the open mobile drawer would stack two backdrops
		// (drawer z-45 + overlay z-1100) and double-dim the page. The
		// drawer has no business staying open under a modal.
		if (open && window.mobileNav) {
			window.mobileNav.close();
		}

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
