const DB_NAME = 'KuraDigitalOffline';
const DB_VERSION = 1;
const STORE_NAME = 'pendingReports';

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                store.createIndex('clientId', 'clientId', { unique: true });
                store.createIndex('campaignId', 'campaignId', { unique: false });
                store.createIndex('createdAt', 'createdAt', { unique: false });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function saveReport(report) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const request = store.add({
            ...report,
            createdAt: new Date().toISOString(),
        });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function getAllPending(campaignId) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);

        if (campaignId) {
            const index = store.index('campaignId');
            const request = index.getAll(campaignId);
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        } else {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        }
    });
}

export async function getPendingCount(campaignId) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const store = tx.objectStore(STORE_NAME);

        if (campaignId) {
            const index = store.index('campaignId');
            const request = index.count(campaignId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        } else {
            const request = store.count();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        }
    });
}

export async function deleteReport(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const request = tx.objectStore(STORE_NAME).delete(id);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

export async function clearAll() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const request = tx.objectStore(STORE_NAME).clear();
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}
