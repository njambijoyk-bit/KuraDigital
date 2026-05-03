<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\PollingStation;
use App\Models\User;

class PollingStationPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.view', $campaign);
    }

    public function view(User $user, PollingStation $station): bool
    {
        return $user->campaignCan('eday.view', $station->campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.command-centre', $campaign);
    }

    public function update(User $user, PollingStation $station): bool
    {
        return $user->campaignCan('eday.command-centre', $station->campaign);
    }

    public function delete(User $user, PollingStation $station): bool
    {
        return $user->campaignCan('eday.command-centre', $station->campaign);
    }
}
