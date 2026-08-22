/**
 * Realtime -> local cache bridge (hybrid Slice 3, brief §13, §15).
 *
 * When Echo has a live socket, the team channel becomes the freshness
 * signal: any domain event triggers one debounced catch-up pull through the
 * SAME sync pipeline the poller uses (cursor -> deltas -> IndexedDB), so
 * realtime and polling can never disagree about what the cache holds. While
 * the socket is connected the poller stretches to a slow heartbeat; the
 * moment it drops, normal polling resumes (brief §37) and reconnection
 * starts with a catch-up pull to cover the gap.
 */

import * as syncClient from './syncClient';

const CATCH_UP_DEBOUNCE_MS = 2000;

let debounceTimer = null;

function scheduleCatchUp() {
	clearTimeout(debounceTimer);
	debounceTimer = setTimeout(() => syncClient.requestCatchUp(), CATCH_UP_DEBOUNCE_MS);
}

export function boot(context) {
	if (!window.Echo) {
		return false;
	}

	window.Echo.private(`team.${context.teamId}`).listenToAll(() => scheduleCatchUp());

	// Pusher-protocol connection state drives the polling cadence and the
	// user's connectivity pill.
	const connection = window.Echo.connector?.pusher?.connection;
	if (connection) {
		connection.bind('state_change', ({ current }) => {
			const connected = current === 'connected';
			syncClient.setRealtimeConnected(connected);

			if (connected) {
				// Cover whatever happened while the socket was down.
				syncClient.requestCatchUp();
			}
			// A dead socket is NOT an error state: polling is the sanctioned
			// fallback (brief §37) and the poller owns the pill. Losing the
			// socket only restores the faster poll cadence.
		});
	}

	return true;
}
