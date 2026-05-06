<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class WardTargetPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.view-electoral-math', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.view-electoral-math', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.edit-ward-targets', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.edit-ward-targets', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.edit-ward-targets', $campaign);
    }
}
