<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class SitePolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('site.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('site.manage-settings') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('site.edit') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('site.manage-settings') && $user->isMemberOf($campaign);
    }
}
