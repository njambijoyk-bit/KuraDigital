<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('events.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('events.create') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('events.edit') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('events.delete') && $user->isMemberOf($campaign);
    }
}
