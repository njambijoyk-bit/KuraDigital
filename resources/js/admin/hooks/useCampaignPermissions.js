import { useMemo } from 'react';
import { useParams } from 'react-router-dom';
import useAuthStore from '../stores/useAuthStore';

const PLATFORM_ROLES = ['platform-owner', 'platform-support'];

export default function useCampaignPermissions() {
    const { campaignId } = useParams();
    const campaigns = useAuthStore((s) => s.campaigns);
    const globalRoles = useAuthStore((s) => s.globalRoles);
    const user = useAuthStore((s) => s.user);

    return useMemo(() => {
        const id = parseInt(campaignId, 10);
        const membership = campaigns.find((c) => c.id === id) || null;
        const isPlatformOwner = globalRoles.includes('platform-owner');
        const isPlatformSupport = globalRoles.includes('platform-support');
        const isPlatformRole = isPlatformOwner || isPlatformSupport;
        const permissions = new Set(membership?.permissions || []);
        const role = membership?.role || null;

        const can = (permission) => {
            if (isPlatformOwner) return true;
            return permissions.has(permission);
        };

        const canAny = (...perms) => perms.some((p) => can(p));

        const hasRole = (...roles) => {
            if (isPlatformOwner) return true;
            return roles.includes(role);
        };

        return {
            role,
            permissions,
            membership,
            isPlatformOwner,
            isPlatformSupport,
            isPlatformRole,
            clearanceLevel: user?.clearance_level || 'public',
            can,
            canAny,
            hasRole,
        };
    }, [campaignId, campaigns, globalRoles, user]);
}
