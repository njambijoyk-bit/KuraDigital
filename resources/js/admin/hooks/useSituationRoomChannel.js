import { useEffect, useRef } from 'react';

/**
 * Subscribe to Situation Room real-time updates via Laravel Echo.
 *
 * Listens on `election-day.{campaignId}` for:
 *   - .situation-room.update  (activity stream + staleness flags)
 *   - .tally.submitted
 *   - .tally.verified
 *   - .incident.reported
 *
 * @param {number} campaignId
 * @param {Object} handlers - { onSituationRoomUpdate, onTallySubmitted, onTallyVerified, onIncidentReported }
 */
export default function useSituationRoomChannel(campaignId, handlers = {}) {
    const handlersRef = useRef(handlers);
    handlersRef.current = handlers;

    useEffect(() => {
        if (!window.Echo || !campaignId) return;

        const channelName = `election-day.${campaignId}`;
        const channel = window.Echo.channel(channelName);

        channel
            .listen('.situation-room.update', (e) => {
                handlersRef.current.onSituationRoomUpdate?.(e);
            })
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
