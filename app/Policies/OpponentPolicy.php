<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class OpponentPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.create', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.delete', $campaign);
    }

    public function viewResearch(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.view-research', $campaign);
    }

    public function addResearch(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.add-research', $campaign);
    }

    public function editResearch(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.edit-research', $campaign);
    }

    public function deleteResearch(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('opponents.delete-research', $campaign);
    }
}
