<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('media.view', $campaign);
    }

    public function view(User $user, Media $media): bool
    {
        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            return true;
        }

        return $user->campaignCan('media.view', $media->campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('media.upload', $campaign);
    }

    public function update(User $user, Media $media): bool
    {
        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignCan('media.manage', $media->campaign);
    }

    public function delete(User $user, Media $media): bool
    {
        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignCan('media.delete', $media->campaign);
    }
}
