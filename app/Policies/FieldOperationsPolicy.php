<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class FieldOperationsPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view', $campaign);
    }

    public function manageAgents(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.manage-agents', $campaign);
    }

    public function viewAgentLocations(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view-agent-locations', $campaign);
    }

    public function assignStations(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.assign-stations', $campaign);
    }

    public function submitSurvey(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.submit-survey', $campaign);
    }

    public function submitCheckIn(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.submit-checkin', $campaign);
    }

    public function viewReports(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view-reports', $campaign);
    }

    public function createReports(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.create-reports', $campaign);
    }
}
