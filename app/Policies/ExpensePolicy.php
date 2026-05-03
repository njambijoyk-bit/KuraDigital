<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.log-expense', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.edit', $campaign);
    }

    public function approve(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('finance.approve-expense', $campaign);
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
