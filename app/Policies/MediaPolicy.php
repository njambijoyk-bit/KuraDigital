<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('media.view') && $user->isMemberOf($campaign);
    }

    public function view(User $user, Media $media): bool
    {
        if (!$user->can('media.view')) {
            return false;
        }

        return $user->hasRole(['platform-owner', 'platform-support']) ||
            $user->isMemberOf($media->campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('media.upload') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Media $media): bool
    {
        if (!$user->can('media.manage')) {
            return false;
        }

        return $user->hasRole('platform-owner') || $user->isMemberOf($media->campaign);
    }

    public function delete(User $user, Media $media): bool
    {
        if (!$user->can('media.delete')) {
            return false;
        }

        return $user->hasRole('platform-owner') || $user->isMemberOf($media->campaign);
    }
}
