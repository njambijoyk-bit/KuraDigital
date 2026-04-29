<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class ManifestoPillarPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('manifesto.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('manifesto.create') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('manifesto.edit') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('manifesto.delete') && $user->isMemberOf($campaign);
    }
}
