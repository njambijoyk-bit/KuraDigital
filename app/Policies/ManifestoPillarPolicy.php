<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class ManifestoPillarPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('manifesto.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('manifesto.create', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('manifesto.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('manifesto.delete', $campaign);
    }
}
