import { useEffect, useRef } from 'react';

/**
 * Subscribe to real-time election day events via Laravel Echo.
 *
 * When broadcasting is configured (VITE_BROADCAST_DRIVER=reverb|pusher),
 * this hook listens on `election-day.{campaignId}` for:
 *   - .tally.submitted
 *   - .tally.verified
 *   - .incident.reported
 *
 * Falls back gracefully to no-op when Echo is not available (broadcast driver = null).
 *
 * @param {number} campaignId
 * @param {Object} handlers - { onTallySubmitted, onTallyVerified, onIncidentReported }
 */
export default function useElectionDayChannel(campaignId, handlers = {}) {
    const handlersRef = useRef(handlers);
    handlersRef.current = handlers;

    useEffect(() => {
        if (!window.Echo || !campaignId) return;

        const channelName = `election-day.${campaignId}`;
        const channel = window.Echo.channel(channelName);

        channel
            .listen('.tally.submitted', (e) => {
                handlersRef.current.onTallySubmitted?.(e);
            })
            .listen('.tally.verified', (e) => {
                handlersRef.current.onTallyVerified?.(e);
            })
            .listen('.incident.reported', (e) => {
                handlersRef.current.onIncidentReported?.(e);
            });

        return () => {
            window.Echo.leave(channelName);
        };
    }, [campaignId]);
}
