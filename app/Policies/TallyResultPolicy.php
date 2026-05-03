<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\TallyResult;
use App\Models\User;

class TallyResultPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.view-tallies', $campaign);
    }

    public function view(User $user, TallyResult $result): bool
    {
        return $user->campaignCan('eday.view-tallies', $result->campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.submit-results', $campaign);
    }

    public function update(User $user, TallyResult $result): bool
    {
        return $user->campaignCan('eday.submit-results', $result->campaign);
    }

    public function verify(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('eday.command-centre', $campaign);
    }

    public function delete(User $user, TallyResult $result): bool
    {
        return $user->campaignCan('eday.command-centre', $result->campaign);
    }
}
