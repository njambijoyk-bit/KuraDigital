<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('contacts.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('contacts.view', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('contacts.respond', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('contacts.archive', $campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('contacts.export', $campaign);
    }
}
