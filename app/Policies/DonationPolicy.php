<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class DonationPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.view-donations', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.view-donations', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.edit', $campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.export', $campaign);
    }
}
