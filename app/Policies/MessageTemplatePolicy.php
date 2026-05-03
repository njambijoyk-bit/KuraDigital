<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class MessageTemplatePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.create', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.edit', $campaign);
    }

    public function approve(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.approve', $campaign);
    }
}
