import { create } from 'zustand';
import { saveReport, getAllPending, getPendingCount, deleteReport } from './offlineDb';
import api from './api';

const useOfflineSyncStore = create((set, get) => ({
    isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
    pendingCount: 0,
    syncing: false,
    lastSyncAt: null,
    syncResults: [],

    init: () => {
        if (typeof window === 'undefined') return;

        const updateOnline = () => set({ isOnline: navigator.onLine });
        window.addEventListener('online', () => {
            updateOnline();
            get().syncPending();
        });
        window.addEventListener('offline', updateOnline);

        // Listen for SW sync messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data?.type === 'SYNC_PROGRESS' || event.data?.type === 'SYNC_COMPLETE') {
                    set({ syncResults: event.data.results || [] });
                    get().refreshCount();
                }
            });
        }

        get().refreshCount();
    },

    refreshCount: async () => {
        try {
            const count = await getPendingCount();
            set({ pendingCount: count });
        } catch {
            // IndexedDB not available
        }
    },

    queueReport: async (reportData) => {
        const token = localStorage.getItem('kura_token') || '';
        const clientId = crypto.randomUUID();

        const entry = {
            ...reportData,
            clientId,
            token,
            queuedAt: new Date().toISOString(),
        };

        await saveReport(entry);
        set((state) => ({ pendingCount: state.pendingCount + 1 }));

        // Request background sync
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            const reg = await navigator.serviceWorker.ready;
            try {
                await reg.sync.register('field-report-sync');
            } catch {
                // Background sync not available — will sync manually
            }
        }

        // If online, try to sync immediately
        if (navigator.onLine) {
            get().syncPending();
        }

        return clientId;
    },

    syncPending: async () => {
        if (get().syncing || !navigator.onLine) return;
        set({ syncing: true });

        try {
            const reports = await getAllPending();
            if (reports.length === 0) {
                set({ syncing: false });
                return;
            }

            const results = [];
            const token = localStorage.getItem('kura_token') || '';

            for (const report of reports) {
                try {
                    const formData = new FormData();
                    formData.append('type', report.type);
                    formData.append('client_id', report.clientId);
                    if (report.title) formData.append('title', report.title);
                    if (report.body) formData.append('body', report.body);
                    if (report.latitude) formData.append('latitude', String(report.latitude));
                    if (report.longitude) formData.append('longitude', String(report.longitude));
                    if (report.ward) formData.append('ward', report.ward);
                    if (report.constituency) formData.append('constituency', report.constituency);
                    if (report.county) formData.append('county', report.county);
                    if (report.capturedAt) formData.append('captured_at', report.capturedAt);
                    if (report.tags) {
                        report.tags.forEach((t) => formData.append('tags[]', t));
                    }

                    // Re-attach file blobs
                    if (report.files && report.files.length > 0) {
                        for (const file of report.files) {
                            formData.append('files[]', file.blob, file.name);
                        }
                    }

                    await api.post(
                        `/campaigns/${report.campaignId}/field-reports`,
                        formData,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data',
                                Authorization: `Bearer ${report.token || token}`,
                            },
                        }
                    );

                    await deleteReport(report.id);
                    results.push({ clientId: report.clientId, status: 'synced' });
                } catch (err) {
                    results.push({
                        clientId: report.clientId,
                        status: 'failed',
                        error: err.response?.data?.message || err.message,
                    });
                }
            }

            set({
                syncResults: results,
                lastSyncAt: new Date().toISOString(),
            });
        } catch {
            // silent
        }

        await get().refreshCount();
        set({ syncing: false });
    },

    getPending: async (campaignId) => {
        return getAllPending(campaignId);
    },
}));

export default useOfflineSyncStore;
