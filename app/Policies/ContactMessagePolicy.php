<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('contacts.view') && $user->isMemberOf($campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('contacts.view') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('contacts.respond') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('contacts.archive') && $user->isMemberOf($campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        return $user->can('contacts.export') && $user->isMemberOf($campaign);
    }
}
