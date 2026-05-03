<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAnalytics', [Report::class, $campaign]);

        $members = $campaign->members()->where('is_active', true)->count();
        $voters = $campaign->voters()->count();
        $fieldReports = $campaign->fieldReports()->count();
        $totalDonations = $campaign->donations()->where('status', 'completed')->sum('amount');
        $totalBudget = $campaign->budgets()->sum('allocated_amount');
        $totalSpent = $campaign->budgets()->sum('spent_amount');
        $incidents = $campaign->incidents()->count();
        $stations = $campaign->pollingStations()->count();

        return response()->json([
            'team_members' => $members,
            'voters' => $voters,
            'field_reports' => $fieldReports,
            'total_donations' => (float) $totalDonations,
            'total_budget' => (float) $totalBudget,
            'total_spent' => (float) $totalSpent,
            'budget_remaining' => (float) $totalBudget - (float) $totalSpent,
            'incidents' => $incidents,
            'polling_stations' => $stations,
        ]);
    }

    public function voterGrowth(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewDashboards', [Report::class, $campaign]);

        $days = (int) $request->input('days', 30);
        $days = min($days, 365);

        $startDate = now()->subDays($days);

        $growth = $campaign->voters()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $total = $campaign->voters()->count();
        $periodCount = array_sum($growth);

        return response()->json([
            'total' => $total,
            'period_count' => $periodCount,
            'daily' => $growth,
        ]);
    }

    public function fieldActivity(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewDashboards', [Report::class, $campaign]);

        $days = (int) $request->input('days', 30);
        $days = min($days, 365);
        $startDate = now()->subDays($days);

        $checkIns = $campaign->checkIns()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $reports = $campaign->fieldReports()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $agentActivity = $campaign->fieldAgents()
            ->withCount(['checkIns' => fn ($q) => $q->where('created_at', '>=', $startDate)])
            ->orderByDesc('check_ins_count')
            ->limit(10)
            ->get(['id', 'user_id'])
            ->map(fn ($a) => [
                'agent_id' => $a->id,
                'check_ins' => $a->check_ins_count,
            ]);

        return response()->json([
            'check_ins_daily' => $checkIns,
            'reports_daily' => $reports,
            'top_agents' => $agentActivity,
        ]);
    }

    public function financeTrends(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewDashboards', [Report::class, $campaign]);

        $days = (int) $request->input('days', 30);
        $days = min($days, 365);
        $startDate = now()->subDays($days);

        $expensesDaily = $campaign->expenses()
            ->where('status', 'approved')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, sum(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $donationsDaily = $campaign->donations()
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, sum(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $budgetUtilization = $campaign->budgets()
            ->selectRaw('category, sum(allocated_amount) as allocated, sum(spent_amount) as spent')
            ->groupBy('category')
            ->get()
            ->map(fn ($b) => [
                'category' => $b->category,
                'allocated' => (float) $b->allocated,
                'spent' => (float) $b->spent,
                'utilization' => $b->allocated > 0 ? round(($b->spent / $b->allocated) * 100, 1) : 0,
            ]);

        return response()->json([
            'expenses_daily' => $expensesDaily,
            'donations_daily' => $donationsDaily,
            'budget_utilization' => $budgetUtilization,
        ]);
    }

    public function geographicBreakdown(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewDashboards', [Report::class, $campaign]);

        $votersByWard = $campaign->voters()
            ->whereNotNull('ward')
            ->selectRaw('ward, count(*) as count')
            ->groupBy('ward')
            ->orderByDesc('count')
            ->pluck('count', 'ward')
            ->toArray();

        $votersByConstituency = $campaign->voters()
            ->whereNotNull('constituency')
            ->selectRaw('constituency, count(*) as count')
            ->groupBy('constituency')
            ->orderByDesc('count')
            ->pluck('count', 'constituency')
            ->toArray();

        $incidentsByWard = $campaign->incidents()
            ->whereNotNull('ward')
            ->selectRaw('ward, count(*) as count')
            ->groupBy('ward')
            ->orderByDesc('count')
            ->pluck('count', 'ward')
            ->toArray();

        $stationsByConstituency = $campaign->pollingStations()
            ->whereNotNull('constituency')
            ->selectRaw('constituency, count(*) as count')
            ->groupBy('constituency')
            ->orderByDesc('count')
            ->pluck('count', 'constituency')
            ->toArray();

        return response()->json([
            'voters_by_ward' => $votersByWard,
            'voters_by_constituency' => $votersByConstituency,
            'incidents_by_ward' => $incidentsByWard,
            'stations_by_constituency' => $stationsByConstituency,
        ]);
    }
}
