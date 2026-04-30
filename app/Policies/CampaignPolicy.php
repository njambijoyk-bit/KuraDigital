<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['platform-owner', 'platform-support'])
            || $user->activeMemberships()->exists();
    }

    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            return true;
        }

        return $user->campaignCan('campaign.view', $campaign);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->activeMemberships()
            ->whereIn('role', ['campaign-owner', 'campaign-director', 'deputy-campaign-director'])
            ->exists();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignCan('campaign.edit', $campaign) &&
            $user->campaignHasRole(['campaign-owner', 'campaign-director', 'deputy-campaign-director'], $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignCan('campaign.delete', $campaign) &&
            $user->campaignHasRole('campaign-owner', $campaign);
    }

    public function manageSettings(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignCan('campaign.manage-settings', $campaign) &&
            $user->campaignHasRole(['campaign-owner', 'campaign-director'], $campaign);
    }

    public function viewChildren(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            return true;
        }

        if (!$user->campaignCan('campaign.view-all-children', $campaign)) {
            return false;
        }

        $membership = $user->membershipFor($campaign);
        return $membership && in_array($membership->visibility_scope, ['constituency', 'county', 'national']);
    }
}
