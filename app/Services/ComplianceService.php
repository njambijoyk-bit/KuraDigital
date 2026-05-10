<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignFinanceSetting;
use App\Models\ComplianceAlert;
use App\Models\Donation;
use App\Models\Expense;

class ComplianceService
{
    public function __construct(
        private FinanceAbacService $abacService,
    ) {}

    public function getDashboardData(Campaign $campaign): array
    {
        $settings = CampaignFinanceSetting::where('campaign_id', $campaign->id)->first();
        $score = $this->calculateScore($campaign, $settings);
        $capUsage = $this->abacService->getSpendingCapUsage($campaign);

        $unresolvedAlerts = ComplianceAlert::where('campaign_id', $campaign->id)
            ->unresolved()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $alertCounts = ComplianceAlert::where('campaign_id', $campaign->id)
            ->unresolved()
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $flaggedDonations = Donation::where('campaign_id', $campaign->id)
            ->whereNotNull('compliance_flags')
            ->orderByDesc('donated_at')
            ->limit(10)
            ->get();

        $donationsBySource = Donation::where('campaign_id', $campaign->id)
            ->where('status', 'completed')
            ->selectRaw('source_type, count(*) as count, sum(amount) as total')
            ->groupBy('source_type')
            ->get()
            ->keyBy('source_type')
            ->toArray();

        return [
            'score' => $score,
            'spending_cap' => $capUsage,
            'alerts' => $unresolvedAlerts,
            'alert_counts' => $alertCounts,
            'flagged_donations' => $flaggedDonations,
            'donations_by_source' => $donationsBySource,
            'settings' => $settings,
        ];
    }

    public function calculateScore(Campaign $campaign, ?CampaignFinanceSetting $settings = null): array
    {
        $settings ??= CampaignFinanceSetting::where('campaign_id', $campaign->id)->first();

        $factors = [];
        $totalWeight = 0;
        $weightedScore = 0;

        // Factor 1: Spending cap compliance (weight: 30)
        $capUsage = $this->abacService->getSpendingCapUsage($campaign);
        if ($capUsage) {
            $capScore = max(0, 100 - max(0, $capUsage['percent'] - 80) * 5);
            $factors['spending_cap'] = ['score' => $capScore, 'label' => 'Spending Cap'];
            $weightedScore += $capScore * 30;
            $totalWeight += 30;
        }

        // Factor 2: Expense audit completeness (weight: 25)
        $totalExpenses = Expense::where('campaign_id', $campaign->id)->count();
        $approvedOrRejected = Expense::where('campaign_id', $campaign->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->count();
        $auditScore = $totalExpenses > 0 ? round(($approvedOrRejected / $totalExpenses) * 100) : 100;
        $factors['audit_completeness'] = ['score' => $auditScore, 'label' => 'Audit Completeness'];
        $weightedScore += $auditScore * 25;
        $totalWeight += 25;

        // Factor 3: Donation compliance — flagged items (weight: 25)
        $totalDonations = Donation::where('campaign_id', $campaign->id)->count();
        $flaggedDonations = Donation::where('campaign_id', $campaign->id)
            ->whereNotNull('compliance_flags')
            ->count();
        $donationScore = $totalDonations > 0 ? round((1 - ($flaggedDonations / $totalDonations)) * 100) : 100;
        $factors['donation_compliance'] = ['score' => $donationScore, 'label' => 'Donation Compliance'];
        $weightedScore += $donationScore * 25;
        $totalWeight += 25;

        // Factor 4: Unresolved alerts (weight: 20)
        $unresolvedCount = ComplianceAlert::where('campaign_id', $campaign->id)->unresolved()->count();
        $criticalCount = ComplianceAlert::where('campaign_id', $campaign->id)->unresolved()->bySeverity('critical')->count();
        $alertScore = max(0, 100 - ($unresolvedCount * 5) - ($criticalCount * 15));
        $factors['alerts'] = ['score' => $alertScore, 'label' => 'Alert Resolution'];
        $weightedScore += $alertScore * 20;
        $totalWeight += 20;

        $overall = $totalWeight > 0 ? round($weightedScore / $totalWeight) : 100;

        return [
            'overall' => $overall,
            'status' => $overall >= 80 ? 'healthy' : ($overall >= 60 ? 'warning' : 'critical'),
            'factors' => $factors,
        ];
    }

    public function checkAndCreateAlerts(Campaign $campaign): void
    {
        $this->checkSpendingCapAlerts($campaign);
        $this->checkPendingExpenseAlerts($campaign);
    }

    public function checkDonationCompliance(Donation $donation, Campaign $campaign): array
    {
        $flags = [];
        $result = $this->abacService->canAcceptDonation($donation, $campaign);

        if ($result->status === 'denied') {
            $flags[] = 'donation_cap_exceeded';
            $this->createAlert($campaign, 'donation_cap_exceeded', 'critical',
                'Donation cap exceeded',
                $result->message,
                $donation
            );
        }

        if ($result->status === 'disclosure_required') {
            $flags[] = 'requires_kyc';
            $this->createAlert($campaign, 'missing_kyc', 'warning',
                'Large donation requires KYC verification',
                $result->message,
                $donation
            );
        }

        if ($donation->source_type === 'foreign') {
            $flags[] = 'foreign_source';
        }

        return $flags;
    }

    public function generateIEBCReport(Campaign $campaign, ?string $startDate = null, ?string $endDate = null): array
    {
        $expenseQuery = Expense::where('campaign_id', $campaign->id)->where('status', 'approved');
        $donationQuery = Donation::where('campaign_id', $campaign->id)->where('status', 'completed');

        if ($startDate) {
            $expenseQuery->where('expense_date', '>=', $startDate);
            $donationQuery->where('donated_at', '>=', $startDate);
        }
        if ($endDate) {
            $expenseQuery->where('expense_date', '<=', $endDate);
            $donationQuery->where('donated_at', '<=', $endDate);
        }

        $expenses = $expenseQuery->get();
        $donations = $donationQuery->get();

        $expensesByCategory = $expenses->groupBy('category')->map(fn ($group) => [
            'count' => $group->count(),
            'total' => $group->sum('amount'),
        ]);

        $donationsBySource = $donations->groupBy('source_type')->map(fn ($group) => [
            'count' => $group->count(),
            'total' => $group->sum('amount'),
        ]);

        $settings = CampaignFinanceSetting::where('campaign_id', $campaign->id)->first();

        return [
            'campaign' => [
                'name' => $campaign->name,
                'level' => $campaign->level,
                'county' => $campaign->county,
                'constituency' => $campaign->constituency,
            ],
            'period' => ['start' => $startDate, 'end' => $endDate],
            'spending_cap' => $settings?->spending_cap,
            'total_expenditure' => $expenses->sum('amount'),
            'total_donations' => $donations->sum('amount'),
            'expenses_by_category' => $expensesByCategory,
            'donations_by_source' => $donationsBySource,
            'expense_count' => $expenses->count(),
            'donation_count' => $donations->count(),
            'disclosable_donations' => $donations->where('requires_disclosure', true)->count(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function checkSpendingCapAlerts(Campaign $campaign): void
    {
        $capUsage = $this->abacService->getSpendingCapUsage($campaign);
        if (!$capUsage) {
            return;
        }

        $settings = CampaignFinanceSetting::where('campaign_id', $campaign->id)->first();
        if (!$settings) {
            return;
        }

        $existing = ComplianceAlert::where('campaign_id', $campaign->id)
            ->whereIn('type', ['spending_cap_warning', 'spending_cap_critical'])
            ->unresolved()
            ->exists();

        if ($existing) {
            return;
        }

        if ($capUsage['percent'] >= $settings->critical_at_percent) {
            $this->createAlert($campaign, 'spending_cap_critical', 'critical',
                'Spending at ' . $capUsage['percent'] . '% of legal cap',
                'Campaign spending has reached KES ' . number_format($capUsage['spent'], 2)
                . ' of the KES ' . number_format($capUsage['cap'], 2) . ' cap.'
            );
        } elseif ($capUsage['percent'] >= $settings->alert_at_percent) {
            $this->createAlert($campaign, 'spending_cap_warning', 'warning',
                'Spending at ' . $capUsage['percent'] . '% of legal cap',
                'Campaign spending has reached KES ' . number_format($capUsage['spent'], 2)
                . ' of the KES ' . number_format($capUsage['cap'], 2) . ' cap.'
            );
        }
    }

    private function checkPendingExpenseAlerts(Campaign $campaign): void
    {
        $pendingCount = Expense::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        if ($pendingCount > 0) {
            $existing = ComplianceAlert::where('campaign_id', $campaign->id)
                ->ofType('stale_pending_expenses')
                ->unresolved()
                ->exists();

            if (!$existing) {
                $this->createAlert($campaign, 'stale_pending_expenses', 'info',
                    $pendingCount . ' expenses pending for over 7 days',
                    'There are ' . $pendingCount . ' expenses that have been pending approval for more than 7 days.'
                );
            }
        }
    }

    private function createAlert(
        Campaign $campaign,
        string $type,
        string $severity,
        string $title,
        string $message,
        $alertable = null,
    ): ComplianceAlert {
        $data = [
            'campaign_id' => $campaign->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'created_at' => now(),
        ];

        if ($alertable) {
            $data['alertable_type'] = get_class($alertable);
            $data['alertable_id'] = $alertable->getKey();
        }

        return ComplianceAlert::create($data);
    }
}
