<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class VolunteerPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('volunteers.view') && $user->isMemberOf($campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('volunteers.view') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('volunteers.edit') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('volunteers.delete') && $user->isMemberOf($campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        return $user->can('volunteers.export') && $user->isMemberOf($campaign);
    }
}
