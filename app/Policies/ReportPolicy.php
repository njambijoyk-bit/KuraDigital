<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('reports.view', $campaign);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->campaignCan('reports.view', $report->campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('reports.create', $campaign);
    }

    public function export(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('reports.export', $campaign);
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->campaignCan('reports.create', $report->campaign);
    }

    public function viewAnalytics(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('analytics.view', $campaign);
    }

    public function viewDashboards(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('analytics.view-dashboards', $campaign);
    }
}
