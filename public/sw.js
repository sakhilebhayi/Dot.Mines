/**
 * Dot.Mines service worker (hybrid spec Slice 2): the app-shell parachute.
 *
 * - Navigations are network-first; when the network is gone the cached
 *   /offline shell renders the IndexedDB snapshot (brief §11).
 * - /build/* assets are cache-first: Vite hashes filenames, so a cached
 *   asset can never be stale.
 * - /api/ and /livewire/ are NEVER touched: authenticated dynamic responses
 *   must not land in Cache API (brief §20); IndexedDB holds structured data
 *   instead, written by the in-page sync client.
 */

const SHELL_CACHE = 'dotmines-shell-v1';
const ASSET_CACHE = 'dotmines-assets-v1';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
	event.waitUntil(
		caches.open(SHELL_CACHE).then((cache) => cache.add(OFFLINE_URL)).then(() => self.skipWaiting()),
	);
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(
				keys.filter((key) => key !== SHELL_CACHE && key !== ASSET_CACHE).map((key) => caches.delete(key)),
			))
			.then(() => self.clients.claim()),
	);
});

self.addEventListener('fetch', (event) => {
	const { request } = event;

	if (request.method !== 'GET') return;

	const url = new URL(request.url);

	if (url.origin !== self.location.origin) return;
	if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/livewire')) return;

	if (request.mode === 'navigate') {
		event.respondWith(
			fetch(request).catch(async () => {
				const shell = await caches.match(OFFLINE_URL);
				return shell ?? Response.error();
			}),
		);
		return;
	}

	if (url.pathname.startsWith('/build/')) {
		event.respondWith(
			caches.match(request).then((cached) => cached ?? fetch(request).then((response) => {
				if (response.ok) {
					const copy = response.clone();
					caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
				}
				return response;
			})),
		);
	}
});
