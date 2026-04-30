import React from 'react';
import { ShieldExclamationIcon } from '@heroicons/react/24/outline';
import useCampaignPermissions from '../hooks/useCampaignPermissions';

function AccessDenied() {
    return (
        <div className="flex flex-col items-center justify-center py-20 text-center">
            <ShieldExclamationIcon className="h-16 w-16 text-red-300 mb-4" />
            <h2 className="text-xl font-heading font-bold text-gray-900 mb-2">Access Denied</h2>
            <p className="text-gray-500 max-w-md">
                You do not have the required permissions to view this page.
                Contact your campaign administrator if you believe this is an error.
            </p>
        </div>
    );
}

export default function RequirePermission({ permission, permissions, children }) {
    const { can, canAny } = useCampaignPermissions();

    if (permission && !can(permission)) return <AccessDenied />;
    if (permissions && !canAny(...permissions)) return <AccessDenied />;

    return children;
}
