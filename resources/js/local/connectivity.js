/**
 * Connectivity state machine (hybrid spec Slice 2, brief §10): one explicit,
 * app-wide answer to "is what I'm looking at live?". Pure DOM subscriber
 * model -- no Alpine coupling (Livewire owns Alpine; see .ai/rules/js.md).
 * Elements marked [data-connectivity-pill] render the state.
 */

export const STATES = {
	live: { label: 'Live', dot: 'bg-emerald-400', tone: 'text-emerald-300 border-emerald-400/30' },
	syncing: { label: 'Updating', dot: 'bg-[var(--gold)] animate-pulse', tone: 'text-[var(--gold)] border-[var(--gold)]/30' },
	offline: { label: 'Offline', dot: 'bg-zinc-500', tone: 'text-zinc-400 border-zinc-500/40' },
	sync_error: { label: 'Sync delayed', dot: 'bg-amber-400', tone: 'text-amber-300 border-amber-400/40' },
};

let state = navigator.onLine ? 'syncing' : 'offline';
let lastSyncedAt = null;

function render() {
	const spec = STATES[state] ?? STATES.syncing;

	document.querySelectorAll('[data-connectivity-pill]').forEach((pill) => {
		pill.classList.remove('hidden');
		pill.className = pill.className.replace(/text-\S+|border-\S+/g, '').trim()
			+ ' ' + spec.tone;

		const dot = pill.querySelector('[data-connectivity-dot]');
		if (dot) dot.className = 'inline-block w-2 h-2 rounded-full ' + spec.dot;

		const label = pill.querySelector('[data-connectivity-label]');
		if (label) label.textContent = spec.label;

		pill.title = lastSyncedAt
			? `Local cache updated ${ageLabel(lastSyncedAt)}`
			: 'Local cache not yet populated';
	});
}

export function ageLabel(timestamp) {
	const seconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000));
	if (seconds < 60) return `${seconds}s ago`;
	if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
	return `${Math.floor(seconds / 3600)}h ago`;
}

export function set(next, { syncedAt } = {}) {
	state = next;
	if (syncedAt) lastSyncedAt = syncedAt;
	render();
}

export function current() {
	return state;
}

export function boot() {
	window.addEventListener('online', () => set('syncing'));
	window.addEventListener('offline', () => set('offline'));
	render();
	// Keep the "updated Xs ago" tooltip honest without any network traffic.
	setInterval(render, 10000);
}
