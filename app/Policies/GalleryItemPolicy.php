<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class GalleryItemPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('gallery.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('gallery.create') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('gallery.edit') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('gallery.delete') && $user->isMemberOf($campaign);
    }
}
