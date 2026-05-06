<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportsController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Report::class, $campaign]);

        $query = $campaign->reports()->with('creator:id,name');

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25)));
    }

    public function show(Request $request, Campaign $campaign, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json($report->load('creator:id,name'));
    }

    public function generate(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Report::class, $campaign]);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:voter_summary,field_ops,finance,election_day,strategy,campaign_overview'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parameters' => ['nullable', 'array'],
        ]);

        $type = $validated['type'];
        $title = $validated['title'] ?? $this->defaultTitle($type);
        $data = $this->buildReportData($campaign, $type, $validated['parameters'] ?? []);

        $report = Report::create([
            'campaign_id' => $campaign->id,
            'created_by' => $request->user()->id,
            'title' => $title,
            'description' => $validated['description'] ?? null,
            'type' => $type,
            'parameters' => $validated['parameters'] ?? null,
            'data' => $data,
            'format' => 'json',
            'status' => 'completed',
        ]);

        return response()->json($report->load('creator:id,name'), 201);
    }

    public function destroy(Request $request, Campaign $campaign, Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return response()->json(['message' => 'Report deleted.']);
    }

    public function export(Request $request, Campaign $campaign): Response
    {
        $this->authorize('export', [Report::class, $campaign]);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:voter_summary,field_ops,finance,election_day,strategy,campaign_overview'],
        ]);

        $data = $this->buildReportData($campaign, $validated['type'], $request->input('parameters', []));

        $csv = $this->dataToCsv($validated['type'], $data);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$validated['type']}_report.csv\"",
        ]);
    }

    // =====================================================================
    // Report Data Builders
    // =====================================================================

    private function buildReportData(Campaign $campaign, string $type, array $parameters): array
    {
        return match ($type) {
            'voter_summary' => $this->voterSummary($campaign),
            'field_ops' => $this->fieldOpsSummary($campaign),
            'finance' => $this->financeSummary($campaign),
            'election_day' => $this->electionDaySummary($campaign),
            'strategy' => $this->strategySummary($campaign),
            'campaign_overview' => $this->campaignOverview($campaign),
            default => [],
        };
    }

    private function voterSummary(Campaign $campaign): array
    {
        $voters = $campaign->voters();
        $total = $voters->count();
        $byStatus = $voters->selectRaw('supporter_status, count(*) as count')
            ->groupBy('supporter_status')
            ->pluck('count', 'supporter_status')
            ->toArray();
        $byWard = $voters->selectRaw('ward, count(*) as count')
            ->whereNotNull('ward')
            ->groupBy('ward')
            ->pluck('count', 'ward')
            ->toArray();
        $byConstituency = $voters->selectRaw('constituency, count(*) as count')
            ->whereNotNull('constituency')
            ->groupBy('constituency')
            ->pluck('count', 'constituency')
            ->toArray();
        $recentCount = $campaign->voters()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            'total' => $total,
            'by_supporter_status' => $byStatus,
            'by_ward' => $byWard,
            'by_constituency' => $byConstituency,
            'added_last_30_days' => $recentCount,
        ];
    }

    private function fieldOpsSummary(Campaign $campaign): array
    {
        $agents = $campaign->fieldAgents()->count();
        $surveys = $campaign->surveys()->count();
        $surveyResponses = $campaign->surveys()->withCount('responses')->get()->sum('responses_count');
        $checkIns = $campaign->checkIns()->count();
        $fieldReports = $campaign->fieldReports()->count();

        $checkInsByStatus = $campaign->checkIns()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_agents' => $agents,
            'total_surveys' => $surveys,
            'total_survey_responses' => $surveyResponses,
            'total_check_ins' => $checkIns,
            'check_ins_by_status' => $checkInsByStatus,
            'total_field_reports' => $fieldReports,
        ];
    }

    private function financeSummary(Campaign $campaign): array
    {
        $totalBudget = $campaign->budgets()->sum('allocated_amount');
        $totalSpent = $campaign->budgets()->sum('spent_amount');
        $pendingExpenses = $campaign->expenses()->where('status', 'pending')->sum('amount');
        $approvedExpenses = $campaign->expenses()->where('status', 'approved')->sum('amount');
        $rejectedExpenses = $campaign->expenses()->where('status', 'rejected')->sum('amount');
        $totalDonations = $campaign->donations()->where('status', 'completed')->sum('amount');
        $donationCount = $campaign->donations()->where('status', 'completed')->count();

        $expensesByCategory = $campaign->expenses()
            ->where('status', 'approved')
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $budgetsByCategory = $campaign->budgets()
            ->selectRaw('category, sum(allocated_amount) as allocated, sum(spent_amount) as spent')
            ->groupBy('category')
            ->get()
            ->keyBy('category')
            ->map(fn ($b) => ['allocated' => (float) $b->allocated, 'spent' => (float) $b->spent])
            ->toArray();

        return [
            'total_budget' => (float) $totalBudget,
            'total_spent' => (float) $totalSpent,
            'budget_remaining' => (float) $totalBudget - (float) $totalSpent,
            'pending_expenses' => (float) $pendingExpenses,
            'approved_expenses' => (float) $approvedExpenses,
            'rejected_expenses' => (float) $rejectedExpenses,
            'total_donations' => (float) $totalDonations,
            'donation_count' => $donationCount,
            'net_position' => (float) $totalDonations - (float) $approvedExpenses,
            'expenses_by_category' => $expensesByCategory,
            'budgets_by_category' => $budgetsByCategory,
        ];
    }

    private function electionDaySummary(Campaign $campaign): array
    {
        $totalStations = $campaign->pollingStations()->count();
        $stationsByStatus = $campaign->pollingStations()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalRegistered = $campaign->pollingStations()->sum('registered_voters');

        $tallies = $campaign->tallyResults()->get();
        $reportedStations = $tallies->pluck('polling_station_id')->unique()->count();
        $totalVotesCast = $tallies->groupBy('polling_station_id')
            ->map(fn ($g) => $g->max('total_votes_cast'))
            ->sum();

        $candidateTotals = $tallies->groupBy('candidate_name')->map(function ($group, $name) {
            return [
                'candidate' => $name,
                'party' => $group->first()->party,
                'total_votes' => $group->sum('votes'),
                'stations_reported' => $group->pluck('polling_station_id')->unique()->count(),
            ];
        })->sortByDesc('total_votes')->values()->toArray();

        $totalIncidents = $campaign->incidents()->count();
        $unresolvedIncidents = $campaign->incidents()->where('status', '!=', 'resolved')->count();
        $incidentsBySeverity = $campaign->incidents()
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return [
            'stations' => [
                'total' => $totalStations,
                'by_status' => $stationsByStatus,
                'reported' => $reportedStations,
                'total_registered' => $totalRegistered,
            ],
            'votes' => [
                'total_cast' => $totalVotesCast,
                'turnout_percentage' => $totalRegistered > 0 ? round(($totalVotesCast / $totalRegistered) * 100, 1) : 0,
            ],
            'candidates' => $candidateTotals,
            'incidents' => [
                'total' => $totalIncidents,
                'unresolved' => $unresolvedIncidents,
                'by_severity' => $incidentsBySeverity,
            ],
        ];
    }

    private function strategySummary(Campaign $campaign): array
    {
        $notes = $campaign->strategyNotes()->count();
        $notesByCategory = $campaign->strategyNotes()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $wardTargets = $campaign->wardTargets()->count();
        $polls = $campaign->polls()->count();
        $pollsActive = $campaign->polls()->where('status', 'active')->count();

        $messageTemplates = $campaign->messageTemplates()->count();
        $messageCampaigns = $campaign->messageCampaigns()->count();

        return [
            'strategy_notes' => $notes,
            'notes_by_category' => $notesByCategory,
            'ward_targets' => $wardTargets,
            'polls' => ['total' => $polls, 'active' => $pollsActive],
            'message_templates' => $messageTemplates,
            'message_campaigns' => $messageCampaigns,
        ];
    }

    private function campaignOverview(Campaign $campaign): array
    {
        $members = $campaign->members()->where('is_active', true)->count();
        $membersByRole = $campaign->members()
            ->where('is_active', true)
            ->selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        return [
            'campaign' => [
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'level' => $campaign->level,
                'is_active' => $campaign->is_active,
            ],
            'team' => [
                'total_members' => $members,
                'by_role' => $membersByRole,
            ],
            'voters' => $this->voterSummary($campaign),
            'field_ops' => $this->fieldOpsSummary($campaign),
            'finance' => $this->financeSummary($campaign),
            'strategy' => $this->strategySummary($campaign),
        ];
    }

    // =====================================================================
    // CSV Export
    // =====================================================================

    private function dataToCsv(string $type, array $data): string
    {
        $rows = [];

        switch ($type) {
            case 'voter_summary':
                $rows[] = ['Metric', 'Value'];
                $rows[] = ['Total Voters', $data['total']];
                $rows[] = ['Added Last 30 Days', $data['added_last_30_days']];
                foreach ($data['by_supporter_status'] ?? [] as $status => $count) {
                    $rows[] = ["Status: {$status}", $count];
                }
                foreach ($data['by_ward'] ?? [] as $ward => $count) {
                    $rows[] = ["Ward: {$ward}", $count];
                }
                break;

            case 'finance':
                $rows[] = ['Metric', 'Amount'];
                $rows[] = ['Total Budget', $data['total_budget']];
                $rows[] = ['Total Spent', $data['total_spent']];
                $rows[] = ['Budget Remaining', $data['budget_remaining']];
                $rows[] = ['Pending Expenses', $data['pending_expenses']];
                $rows[] = ['Approved Expenses', $data['approved_expenses']];
                $rows[] = ['Total Donations', $data['total_donations']];
                $rows[] = ['Net Position', $data['net_position']];
                break;

            case 'election_day':
                $rows[] = ['Metric', 'Value'];
                $rows[] = ['Total Stations', $data['stations']['total']];
                $rows[] = ['Reported Stations', $data['stations']['reported']];
                $rows[] = ['Total Registered', $data['stations']['total_registered']];
                $rows[] = ['Total Votes Cast', $data['votes']['total_cast']];
                $rows[] = ['Turnout %', $data['votes']['turnout_percentage']];
                $rows[] = [''];
                $rows[] = ['Candidate', 'Party', 'Votes', 'Stations'];
                foreach ($data['candidates'] ?? [] as $c) {
                    $rows[] = [$c['candidate'], $c['party'] ?? '', $c['total_votes'], $c['stations_reported']];
                }
                break;

            case 'field_ops':
                $rows[] = ['Metric', 'Value'];
                $rows[] = ['Total Agents', $data['total_agents']];
                $rows[] = ['Total Surveys', $data['total_surveys']];
                $rows[] = ['Survey Responses', $data['total_survey_responses']];
                $rows[] = ['Total Check-ins', $data['total_check_ins']];
                $rows[] = ['Field Reports', $data['total_field_reports']];
                break;

            case 'strategy':
                $rows[] = ['Metric', 'Value'];
                $rows[] = ['Strategy Notes', $data['strategy_notes']];
                $rows[] = ['Ward Targets', $data['ward_targets']];
                $rows[] = ['Total Polls', $data['polls']['total']];
                $rows[] = ['Active Polls', $data['polls']['active']];
                $rows[] = ['Message Templates', $data['message_templates']];
                $rows[] = ['Message Campaigns', $data['message_campaigns']];
                break;

            default:
                $rows[] = ['Report Data'];
                $rows[] = [json_encode($data)];
        }

        $output = '';
        foreach ($rows as $row) {
            $output .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', (array) $row)) . "\n";
        }
        return $output;
    }

    private function defaultTitle(string $type): string
    {
        return match ($type) {
            'voter_summary' => 'Voter Summary Report',
            'field_ops' => 'Field Operations Report',
            'finance' => 'Finance Report',
            'election_day' => 'Election Day Report',
            'strategy' => 'Strategy & Messaging Report',
            'campaign_overview' => 'Campaign Overview Report',
            default => 'Report',
        };
    }
}
