import React from 'react';
import useCampaignPermissions from '../hooks/useCampaignPermissions';

export default function PermissionGate({ permission, permissions, fallback = null, children }) {
    const { can, canAny } = useCampaignPermissions();

    if (permission && !can(permission)) return fallback;
    if (permissions && !canAny(...permissions)) return fallback;

    return children;
}
