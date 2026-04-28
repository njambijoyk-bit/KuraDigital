<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaign.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            return true;
        }

        return $user->can('campaign.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user): bool
    {
        return $user->can('campaign.create-child') || $user->hasRole(['platform-owner', 'campaign-owner']);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if (!$user->can('campaign.edit')) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->isMemberOf($campaign) &&
            $user->hasRole(['campaign-owner', 'campaign-director', 'deputy-campaign-director']);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        if (!$user->can('campaign.delete')) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->isMemberOf($campaign) && $user->hasRole('campaign-owner');
    }

    public function manageSettings(User $user, Campaign $campaign): bool
    {
        if (!$user->can('campaign.manage-settings')) {
            return false;
        }

        return $user->hasRole('platform-owner') ||
            ($user->isMemberOf($campaign) && $user->hasRole(['campaign-owner', 'campaign-director']));
    }

    public function viewChildren(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            return true;
        }

        if (!$user->can('campaign.view-all-children')) {
            return false;
        }

        $membership = $user->membershipFor($campaign);
        return $membership && in_array($membership->visibility_scope, ['county', 'national']);
    }
}
