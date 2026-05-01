<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class VoterPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('voters.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('voters.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('voters.create', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('voters.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('voters.delete', $campaign);
    }

    public function import(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('voters.import', $campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('voters.export', $campaign);
    }
}
