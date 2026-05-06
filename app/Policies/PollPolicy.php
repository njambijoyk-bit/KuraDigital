<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class PollPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.view-polls', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.view-polls', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.edit', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('strategy.edit', $campaign);
    }
}
