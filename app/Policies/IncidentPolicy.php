<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.view', $campaign);
    }

    public function view(User $user, Incident $incident): bool
    {
        return $user->campaignCan('eday.view', $incident->campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.report-incidents', $campaign);
    }

    public function update(User $user, Incident $incident): bool
    {
        return $user->campaignCan('eday.report-incidents', $incident->campaign);
    }

    public function resolve(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.command-centre', $campaign);
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->campaignCan('eday.command-centre', $incident->campaign);
    }
}
