<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            return $user->can('audit.view');
        }

        return $user->campaignCan('audit.view', $campaign) &&
            $user->campaignHasRole([
                'campaign-owner', 'campaign-director', 'deputy-campaign-director',
                'finance-director', 'legal-compliance-officer', 'auditor',
            ], $campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole('platform-owner')) {
            return $user->can('audit.export');
        }

        return $user->campaignCan('audit.export', $campaign) &&
            $user->campaignHasRole([
                'campaign-owner', 'campaign-director', 'legal-compliance-officer', 'auditor',
            ], $campaign);
    }
}
