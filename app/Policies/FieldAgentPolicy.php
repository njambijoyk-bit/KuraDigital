<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class FieldAgentPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.manage-agents', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.manage-agents', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.manage-agents', $campaign);
    }

    public function viewLocations(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view-agent-locations', $campaign);
    }

    public function assignStations(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.assign-stations', $campaign);
    }
}
