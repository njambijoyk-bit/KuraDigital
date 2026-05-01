<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class SurveyPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.create-reports', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.create-reports', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.create-reports', $campaign);
    }

    public function submit(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.submit-survey', $campaign);
    }

    public function viewReports(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view-reports', $campaign);
    }
}
