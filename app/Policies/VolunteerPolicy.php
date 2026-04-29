<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class VolunteerPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('volunteers.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('volunteers.view', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('volunteers.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('volunteers.delete', $campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('volunteers.export', $campaign);
    }
}
