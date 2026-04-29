<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class SitePolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('site.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('site.manage-settings', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('site.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('site.manage-settings', $campaign);
    }
}
