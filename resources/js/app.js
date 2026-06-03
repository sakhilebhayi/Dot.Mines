// Theme must be initialised before Alpine mounts so components read
// the correct dark/light class from the start.
import './theme';

import Alpine from 'alpinejs';
window.Alpine = Alpine;

import Chart from 'chart.js/auto';
window.Chart = Chart;
// Do not call `Alpine.start()` here because Livewire v3 bundles Alpine
// and will initialize it. Starting Alpine twice triggers duplicate-instance
// warnings. Mark this Alpine instance as coming from Livewire to avoid
// Livewire's duplicate detection.
if (window.Alpine) {
	window.Alpine.__fromLivewire = true;
}
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
