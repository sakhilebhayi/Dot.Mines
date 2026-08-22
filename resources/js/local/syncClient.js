/**
 * Incremental sync pull loop (hybrid spec Slice 2, brief §6-§7, §31): drains
 * GET /api/v1/sync from the stored cursor into IndexedDB, so the local cache
 * is always at most one interval behind the server while online -- and is
 * the parachute the /offline page renders from when the pit has no signal.
 *
 * Session-cookie authenticated (statefulApi); a 401/419 means the session
 * ended, so the local cache is wiped (brief §20). A stored user/team context
 * different from the page's means an account switch on this browser: wiped
 * too, before a single foreign row could be read (brief §9).
 */

import * as localData from './localData';
import * as connectivity from './connectivity';

const SCOPES = 'fleet,production,notifications,reference';
const SCOPE_STORES = { fleet: 'fleet', production: 'production', notifications: 'notifications', reference: 'reference' };
const TOMBSTONE_STORES = { machines: 'fleet', production_records: 'production', notifications: 'notifications', mine_areas: 'reference' };

const INTERVAL_MS = 60000;
const BACKOFF_MS = [5000, 15000, 60000];
const MAX_PAGES_PER_RUN = 10;

let failures = 0;
let timer = null;
let stopped = false;

async function ensureContext(context) {
	const stored = await localData.getMeta('context');

	if (stored && (stored.userId !== context.userId || stored.teamId !== context.teamId)) {
		await localData.clearAll();
	}

	await localData.setMeta('context', context);
}

async function pullOnce() {
	let cursor = (await localData.getMeta('cursor')) ?? 0;

	for (let page = 0; page < MAX_PAGES_PER_RUN; page++) {
		const response = await fetch(`/api/v1/sync?since=${cursor}&scopes=${SCOPES}`, {
			headers: { Accept: 'application/json' },
			credentials: 'same-origin',
		});

		if (response.status === 401 || response.status === 419) {
			await localData.clearAll();
			stopped = true;
			throw new Error('session-ended');
		}

		if (!response.ok) {
			throw new Error(`sync failed: ${response.status}`);
		}

		const body = await response.json();

		for (const [scope, rows] of Object.entries(body.changes ?? {})) {
			const store = SCOPE_STORES[scope];
			if (store) await localData.upsertMany(store, rows);
		}

		const evictions = {};
		for (const tombstone of body.deleted ?? []) {
			const store = TOMBSTONE_STORES[tombstone.entity_type];
			if (store) (evictions[store] ??= []).push(tombstone.entity_id);
		}
		for (const [store, ids] of Object.entries(evictions)) {
			await localData.removeMany(store, ids);
		}

		cursor = body.version;
		await localData.setMeta('cursor', cursor);

		if (!body.has_more) break;
	}

	await localData.setMeta('lastSyncAt', Date.now());
}

async function run() {
	if (stopped || document.hidden || !navigator.onLine) {
		schedule(INTERVAL_MS);
		if (!navigator.onLine) connectivity.set('offline');
		return;
	}

	connectivity.set('syncing');

	try {
		await pullOnce();
		failures = 0;
		connectivity.set('live', { syncedAt: Date.now() });
		schedule(INTERVAL_MS);
	} catch (error) {
		if (stopped) return;
		failures++;
		connectivity.set(navigator.onLine ? 'sync_error' : 'offline');
		schedule(BACKOFF_MS[Math.min(failures - 1, BACKOFF_MS.length - 1)]);
	}
}

function schedule(delay) {
	if (stopped) return;
	clearTimeout(timer);
	timer = setTimeout(run, delay);
}

export async function boot(context) {
	connectivity.boot();
	await ensureContext(context);

	// Diagnostics hook: forces one pull even in hidden tabs (the normal loop
	// deliberately pauses while hidden, brief §15).
	window.__syncNow = async () => {
		connectivity.set('syncing');
		await pullOnce();
		connectivity.set('live', { syncedAt: Date.now() });
	};

	window.addEventListener('online', () => run());
	document.addEventListener('visibilitychange', () => {
		if (!document.hidden) run();
	});

	run();
}
