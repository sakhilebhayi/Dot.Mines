/**
 * Read-only snapshot renderer for the /offline shell (hybrid spec Slice 2,
 * brief §11): when the network is gone, the service worker serves /offline
 * and this module renders the last-synced fleet, production, and
 * notification state straight from IndexedDB -- clearly labelled as cached,
 * never pretending to be live (brief §12, §37).
 */

import * as localData from './localData';
import { ageLabel } from './connectivity';

// Freshness policies (brief §23): ages beyond these get a stale badge.
const STALE_AFTER_MS = { fleet: 5 * 60 * 1000, production: 30 * 60 * 1000, notifications: 30 * 60 * 1000 };

function el(tag, className, text) {
	const node = document.createElement(tag);
	if (className) node.className = className;
	if (text !== undefined) node.textContent = text;
	return node;
}

function staleBadge(kind, lastSyncAt) {
	if (lastSyncAt && Date.now() - lastSyncAt > STALE_AFTER_MS[kind]) {
		return el('span', 'ml-2 text-xs text-amber-300 border border-amber-400/40 rounded px-1.5 py-0.5', 'Stale');
	}
	return null;
}

function section(title, kind, lastSyncAt) {
	const wrap = el('section', 'bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4');
	const heading = el('h2', 'text-sm font-semibold text-[var(--stone)] mb-3', title);
	const badge = staleBadge(kind, lastSyncAt);
	if (badge) heading.appendChild(badge);
	wrap.appendChild(heading);
	return wrap;
}

export async function render(root) {
	const lastSyncAt = await localData.getMeta('lastSyncAt');

	const banner = el('div', 'text-sm text-[var(--sand)] mb-4');
	banner.textContent = lastSyncAt
		? `Showing cached data — last updated ${ageLabel(lastSyncAt)}`
		: 'No cached data yet. Open the dashboard while online to populate the local cache.';
	root.appendChild(banner);

	if (!lastSyncAt) return;

	const fleet = await localData.all('fleet');
	const fleetSection = section(`Fleet (${fleet.length})`, 'fleet', lastSyncAt);
	const fleetList = el('div', 'space-y-2');
	for (const machine of fleet.slice(0, 50)) {
		const row = el('div', 'flex items-center justify-between gap-3 text-sm');
		row.appendChild(el('span', 'text-[var(--stone)]', machine.name ?? `#${machine.id}`));
		const meta = [machine.status, machine.fuel_level != null ? `${machine.fuel_level}% fuel` : null]
			.filter(Boolean).join(' · ');
		row.appendChild(el('span', 'text-[var(--sand)] text-xs', meta));
		fleetList.appendChild(row);
	}
	fleetSection.appendChild(fleet.length ? fleetList : el('p', 'text-xs text-[var(--sand)]', 'No machines cached.'));
	root.appendChild(fleetSection);

	const production = await localData.all('production');
	const today = new Date().toISOString().slice(0, 10);
	const todayRows = production.filter((r) => r.record_date === today);
	const total = todayRows.reduce((sum, r) => sum + (Number(r.quantity_produced) || 0), 0);
	const prodSection = section("Today's production", 'production', lastSyncAt);
	prodSection.appendChild(el('p', 'text-sm text-[var(--stone)]',
		todayRows.length ? `${total.toLocaleString()} across ${todayRows.length} record(s)` : 'No records cached for today.'));
	root.appendChild(prodSection);

	const notifications = (await localData.all('notifications'))
		.sort((a, b) => (b.sync_version ?? 0) - (a.sync_version ?? 0))
		.slice(0, 10);
	const noteSection = section('Recent notifications', 'notifications', lastSyncAt);
	const noteList = el('div', 'space-y-2');
	for (const note of notifications) {
		const row = el('div', 'text-sm');
		row.appendChild(el('div', 'text-[var(--stone)]', note.title ?? note.type));
		row.appendChild(el('div', 'text-xs text-[var(--sand)]', note.message ?? ''));
		noteList.appendChild(row);
	}
	noteSection.appendChild(notifications.length ? noteList : el('p', 'text-xs text-[var(--sand)]', 'No notifications cached.'));
	root.appendChild(noteSection);
}
