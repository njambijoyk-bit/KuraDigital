<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('projects.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('projects.create') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('projects.edit') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('projects.delete') && $user->isMemberOf($campaign);
    }
}
