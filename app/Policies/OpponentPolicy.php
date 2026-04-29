<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class OpponentPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.create') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.edit') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.delete') && $user->isMemberOf($campaign);
    }

    public function viewResearch(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.view-research') && $user->isMemberOf($campaign);
    }

    public function addResearch(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.add-research') && $user->isMemberOf($campaign);
    }

    public function editResearch(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.edit-research') && $user->isMemberOf($campaign);
    }

    public function deleteResearch(User $user, Campaign $campaign): bool
    {
        return $user->can('opponents.delete-research') && $user->isMemberOf($campaign);
    }
}
