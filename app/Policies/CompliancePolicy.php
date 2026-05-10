<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\CampaignFinanceSetting;
use App\Models\User;

class CompliancePolicy
{
    public function viewCompliance(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('compliance.view', $campaign)
            || $user->campaignCan('finance.view', $campaign);
    }

    public function manageSettings(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('compliance.manage-settings', $campaign);
    }

    public function resolveAlerts(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('compliance.resolve-alerts', $campaign);
    }

    public function generateReports(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('compliance.generate-reports', $campaign)
            || $user->campaignCan('finance.export', $campaign);
    }

    public function override(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('compliance.override', $campaign);
    }
}
