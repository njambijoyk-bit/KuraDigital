<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CheckInPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.submit-checkin', $campaign);
    }
}
