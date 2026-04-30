<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\User;

class CampaignMemberPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('team.view', $campaign);
    }

    public function invite(User $user, Campaign $campaign): bool
    {
        if (!$user->campaignCan('team.invite', $campaign)) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignHasRole([
            'campaign-owner', 'campaign-director', 'deputy-campaign-director',
            'field-director', 'communications-director', 'digital-director',
            'voter-outreach-director', 'regional-coordinator',
        ], $campaign);
    }

    public function update(User $user, CampaignMember $member): bool
    {
        if ($user->id === $member->user_id) {
            return false;
        }

        $campaign = $member->campaign;

        if (!$user->campaignCan('team.assign-roles', $campaign)) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignHasRole(['campaign-owner', 'campaign-director', 'deputy-campaign-director'], $campaign);
    }

    public function deactivate(User $user, CampaignMember $member): bool
    {
        $campaign = $member->campaign;

        if (!$user->campaignCan('team.deactivate', $campaign)) {
            return false;
        }

        if ($user->id === $member->user_id) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->campaignHasRole(['campaign-owner', 'campaign-director'], $campaign);
    }
}
