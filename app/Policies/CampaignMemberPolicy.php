<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\User;

class CampaignMemberPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('team.view') && $user->isMemberOf($campaign);
    }

    public function invite(User $user, Campaign $campaign): bool
    {
        if (!$user->can('team.invite')) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        return $user->isMemberOf($campaign) &&
            $user->hasRole([
                'campaign-owner', 'campaign-director', 'deputy-campaign-director',
                'field-director', 'communications-director', 'digital-director',
                'voter-outreach-director', 'regional-coordinator',
            ]);
    }

    public function update(User $user, CampaignMember $member): bool
    {
        if (!$user->can('team.assign-roles')) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        $campaign = $member->campaign;

        return $user->isMemberOf($campaign) &&
            $user->hasRole(['campaign-owner', 'campaign-director', 'deputy-campaign-director']);
    }

    public function deactivate(User $user, CampaignMember $member): bool
    {
        if (!$user->can('team.deactivate')) {
            return false;
        }

        // Cannot deactivate yourself
        if ($user->id === $member->user_id) {
            return false;
        }

        if ($user->hasRole('platform-owner')) {
            return true;
        }

        $campaign = $member->campaign;

        return $user->isMemberOf($campaign) &&
            $user->hasRole(['campaign-owner', 'campaign-director']);
    }
}
