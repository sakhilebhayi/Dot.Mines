/**
 * LocalDataService (hybrid spec Slice 2, brief §5): the browser's local
 * datastore behind one small abstraction. IndexedDB fills the "local SQLite"
 * role -- callers never touch IndexedDB APIs directly, so the mechanism
 * stays replaceable. Holds ONLY what the UI renders (brief §20/§22): never
 * tokens, credentials, or financial data. Cleared whenever the signed-in
 * user or team changes.
 */

const DB_NAME = 'dotmines-local';
const DB_VERSION = 1;

// Entity stores are keyed by server id; `meta` is a key/value store holding
// the sync cursor, timestamps, and the owning user/team context.
export const ENTITY_STORES = ['fleet', 'production', 'notifications', 'reference'];

let dbPromise = null;

function open() {
	if (dbPromise) return dbPromise;

	dbPromise = new Promise((resolve, reject) => {
		const request = indexedDB.open(DB_NAME, DB_VERSION);

		request.onupgradeneeded = () => {
			const db = request.result;
			for (const store of ENTITY_STORES) {
				if (!db.objectStoreNames.contains(store)) {
					db.createObjectStore(store, { keyPath: 'id' });
				}
			}
			if (!db.objectStoreNames.contains('meta')) {
				db.createObjectStore('meta', { keyPath: 'key' });
			}
		};
		request.onsuccess = () => {
			const db = request.result;
			// Self-heal a structurally broken database (e.g. created at the
			// right version by something else, but missing our stores):
			// delete and rebuild once rather than failing every operation.
			const missing = [...ENTITY_STORES, 'meta'].some((store) => ! db.objectStoreNames.contains(store));
			if (missing) {
				db.close();
				dbPromise = null;
				const wipe = indexedDB.deleteDatabase(DB_NAME);
				wipe.onsuccess = () => open().then(resolve, reject);
				wipe.onerror = () => reject(wipe.error);
				return;
			}
			resolve(db);
		};
		request.onerror = () => {
			dbPromise = null;
			reject(request.error);
		};
	});

	return dbPromise;
}

function tx(db, store, mode, work) {
	return new Promise((resolve, reject) => {
		const transaction = db.transaction(store, mode);
		const result = work(transaction.objectStore(store));
		transaction.oncomplete = () => resolve(result?.result ?? result);
		transaction.onerror = () => reject(transaction.error);
	});
}

export async function upsertMany(store, rows) {
	if (!rows.length) return;
	const db = await open();
	await tx(db, store, 'readwrite', (os) => rows.forEach((row) => os.put(row)));
}

export async function removeMany(store, ids) {
	if (!ids.length) return;
	const db = await open();
	await tx(db, store, 'readwrite', (os) => ids.forEach((id) => os.delete(id)));
}

export async function all(store) {
	const db = await open();
	const request = await tx(db, store, 'readonly', (os) => os.getAll());
	return request ?? [];
}

export async function getMeta(key) {
	const db = await open();
	const row = await tx(db, 'meta', 'readonly', (os) => os.get(key));
	return row ? row.value : null;
}

export async function setMeta(key, value) {
	const db = await open();
	await tx(db, 'meta', 'readwrite', (os) => os.put({ key, value }));
}

export async function clearAll() {
	const db = await open();
	for (const store of [...ENTITY_STORES, 'meta']) {
		await tx(db, store, 'readwrite', (os) => os.clear());
	}
}
