import React, { useEffect } from 'react';
import { SignalSlashIcon, CloudArrowUpIcon } from '@heroicons/react/24/outline';
import useOfflineSyncStore from '../../lib/offlineSync';

export default function OfflineIndicator() {
    const { isOnline, pendingCount, syncing, init } = useOfflineSyncStore();

    useEffect(() => {
        init();
    }, [init]);

    if (isOnline && pendingCount === 0) return null;

    return (
        <div className="flex items-center gap-2 text-xs">
            {!isOnline && (
                <span className="flex items-center gap-1 text-orange-600 bg-orange-50 px-2 py-1 rounded-full">
                    <SignalSlashIcon className="h-3.5 w-3.5" />
                    Offline
                </span>
            )}
            {pendingCount > 0 && (
                <span className="flex items-center gap-1 text-blue-600 bg-blue-50 px-2 py-1 rounded-full">
                    <CloudArrowUpIcon className="h-3.5 w-3.5" />
                    {syncing ? 'Syncing...' : `${pendingCount} pending`}
                </span>
            )}
        </div>
    );
}
