<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\FieldReport;
use App\Models\User;

class FieldReportPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.view-reports', $campaign);
    }

    public function view(User $user, Campaign $campaign, FieldReport $report): bool
    {
        if ($report->user_id === $user->id) {
            return true;
        }

        return $user->campaignCan('field.view-reports', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('field.create-reports', $campaign);
    }

    public function update(User $user, Campaign $campaign, FieldReport $report): bool
    {
        if ($report->user_id === $user->id) {
            return true;
        }

        return $user->campaignCan('field.view-reports', $campaign);
    }

    public function delete(User $user, Campaign $campaign, FieldReport $report): bool
    {
        return $user->campaignCan('field.manage-agents', $campaign);
    }
}
