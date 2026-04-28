<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        if (!$user->can('audit.view')) {
            return false;
        }

        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            return true;
        }

        return $user->isMemberOf($campaign) &&
            $user->hasRole([
                'campaign-owner', 'campaign-director', 'deputy-campaign-director',
                'finance-director', 'legal-compliance-officer', 'auditor',
            ]);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        if (!$user->can('audit.export')) {
            return false;
        }

        return $user->hasRole(['platform-owner']) ||
            ($user->isMemberOf($campaign) && $user->hasRole([
                'campaign-owner', 'campaign-director', 'legal-compliance-officer', 'auditor',
            ]));
    }
}
