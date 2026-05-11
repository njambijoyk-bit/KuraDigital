<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Campaign;
use App\Models\JournalLine;

class FinancialReportService
{
    private LedgerService $ledger;

    public function __construct(LedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    public function incomeStatement(Campaign $campaign, ?string $fromDate = null, ?string $toDate = null): array
    {
        $revenueAccounts = $this->getAccountTotals($campaign, 'revenue', $fromDate, $toDate);
        $expenseAccounts = $this->getAccountTotals($campaign, 'expense', $fromDate, $toDate);

        $totalRevenue = collect($revenueAccounts)->sum('balance');
        $totalExpenses = collect($expenseAccounts)->sum('balance');
        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'revenue' => $revenueAccounts,
            'expenses' => $expenseAccounts,
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_income' => round($netIncome, 2),
            'from' => $fromDate,
            'to' => $toDate ?? now()->toDateString(),
        ];
    }

    public function balanceSheet(Campaign $campaign, ?string $asOfDate = null): array
    {
        $assets = $this->getAccountTotals($campaign, 'asset', null, $asOfDate);
        $liabilities = $this->getAccountTotals($campaign, 'liability', null, $asOfDate);
        $equity = $this->getAccountTotals($campaign, 'equity', null, $asOfDate);

        $totalAssets = collect($assets)->sum('balance');
        $totalLiabilities = collect($liabilities)->sum('balance');
        $totalEquity = collect($equity)->sum('balance');

        $revenueTotal = collect($this->getAccountTotals($campaign, 'revenue', null, $asOfDate))->sum('balance');
        $expenseTotal = collect($this->getAccountTotals($campaign, 'expense', null, $asOfDate))->sum('balance');
        $retainedEarnings = $revenueTotal - $expenseTotal;

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity + $retainedEarnings, 2),
            'retained_earnings' => round($retainedEarnings, 2),
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity + $retainedEarnings)) < 0.01,
            'as_of' => $asOfDate ?? now()->toDateString(),
        ];
    }

    public function cashFlow(Campaign $campaign, ?string $fromDate = null, ?string $toDate = null): array
    {
        $cashAccounts = Account::where('campaign_id', $campaign->id)
            ->where('code', 'like', '1%')
            ->where('type', 'asset')
            ->where('is_active', true)
            ->get();

        $movements = [];
        $totalInflow = 0;
        $totalOutflow = 0;

        foreach ($cashAccounts as $account) {
            $query = JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($campaign, $fromDate, $toDate) {
                    $q->where('campaign_id', $campaign->id)->where('is_posted', true);
                    if ($fromDate) {
                        $q->where('date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('date', '<=', $toDate);
                    }
                });

            $debits = (float) (clone $query)->sum('debit');
            $credits = (float) (clone $query)->sum('credit');

            if (abs($debits) < 0.01 && abs($credits) < 0.01) {
                continue;
            }

            $totalInflow += $debits;
            $totalOutflow += $credits;

            $movements[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'inflow' => round($debits, 2),
                'outflow' => round($credits, 2),
                'net' => round($debits - $credits, 2),
            ];
        }

        return [
            'movements' => $movements,
            'total_inflow' => round($totalInflow, 2),
            'total_outflow' => round($totalOutflow, 2),
            'net_cash_flow' => round($totalInflow - $totalOutflow, 2),
            'from' => $fromDate,
            'to' => $toDate ?? now()->toDateString(),
        ];
    }

    public function budgetVsActual(Campaign $campaign, ?string $fromDate = null, ?string $toDate = null): array
    {
        $budgets = Budget::where('campaign_id', $campaign->id)->get();

        $categoryMap = [
            'operations' => '5100',
            'media' => '5200',
            'advertising' => '5200',
            'events' => '5300',
            'field' => '5400',
            'personnel' => '5500',
            'logistics' => '5600',
            'transport' => '5600',
            'other' => '5700',
        ];

        $rows = [];
        $totalBudgeted = 0;
        $totalActual = 0;

        foreach ($budgets as $budget) {
            $accountCode = $categoryMap[$budget->category] ?? '5700';
            $account = Account::where('campaign_id', $campaign->id)
                ->where('code', $accountCode)
                ->first();

            $actual = 0;
            if ($account) {
                $query = JournalLine::where('account_id', $account->id)
                    ->whereHas('journalEntry', function ($q) use ($campaign, $fromDate, $toDate) {
                        $q->where('campaign_id', $campaign->id)->where('is_posted', true);
                        if ($fromDate) {
                            $q->where('date', '>=', $fromDate);
                        }
                        if ($toDate) {
                            $q->where('date', '<=', $toDate);
                        }
                    });

                $actual = (float) $query->sum('debit') - (float) $query->sum('credit');
            }

            $allocated = (float) $budget->allocated_amount;
            $variance = $allocated - $actual;
            $utilizationPercent = $allocated > 0 ? round(($actual / $allocated) * 100, 1) : 0;

            $totalBudgeted += $allocated;
            $totalActual += $actual;

            $rows[] = [
                'budget_id' => $budget->id,
                'budget_name' => $budget->name,
                'category' => $budget->category,
                'budgeted' => round($allocated, 2),
                'actual' => round($actual, 2),
                'variance' => round($variance, 2),
                'utilization_percent' => $utilizationPercent,
                'status' => $utilizationPercent > 100 ? 'over_budget'
                    : ($utilizationPercent > 90 ? 'warning' : 'on_track'),
            ];
        }

        $totalVariance = $totalBudgeted - $totalActual;

        return [
            'items' => $rows,
            'total_budgeted' => round($totalBudgeted, 2),
            'total_actual' => round($totalActual, 2),
            'total_variance' => round($totalVariance, 2),
            'overall_utilization' => $totalBudgeted > 0 ? round(($totalActual / $totalBudgeted) * 100, 1) : 0,
            'from' => $fromDate,
            'to' => $toDate ?? now()->toDateString(),
        ];
    }

    public function fundSummary(Campaign $campaign): array
    {
        $funds = \App\Models\Fund::where('campaign_id', $campaign->id)
            ->where('is_active', true)
            ->get();

        $summary = [];
        foreach ($funds as $fund) {
            $lines = JournalLine::where('fund_id', $fund->id)
                ->whereHas('journalEntry', function ($q) use ($campaign) {
                    $q->where('campaign_id', $campaign->id)->where('is_posted', true);
                });

            $debits = (float) (clone $lines)->sum('debit');
            $credits = (float) (clone $lines)->sum('credit');

            $summary[] = [
                'fund_id' => $fund->id,
                'name' => $fund->name,
                'code' => $fund->code,
                'is_restricted' => $fund->is_restricted,
                'total_debits' => round($debits, 2),
                'total_credits' => round($credits, 2),
                'net_balance' => round($credits - $debits, 2),
            ];
        }

        return $summary;
    }

    private function getAccountTotals(Campaign $campaign, string $type, ?string $fromDate = null, ?string $toDate = null): array
    {
        $accounts = Account::where('campaign_id', $campaign->id)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $results = [];
        foreach ($accounts as $account) {
            $query = JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($campaign, $fromDate, $toDate) {
                    $q->where('campaign_id', $campaign->id)->where('is_posted', true);
                    if ($fromDate) {
                        $q->where('date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('date', '<=', $toDate);
                    }
                });

            $debits = (float) (clone $query)->sum('debit');
            $credits = (float) (clone $query)->sum('credit');

            $balance = $account->isDebitNormal()
                ? $debits - $credits
                : $credits - $debits;

            if (abs($balance) < 0.01) {
                continue;
            }

            $results[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'parent_id' => $account->parent_id,
                'balance' => round($balance, 2),
            ];
        }

        return $results;
    }
}
