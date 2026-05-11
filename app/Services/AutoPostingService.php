<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Expense;
use App\Models\JournalEntry;

class AutoPostingService
{
    private LedgerService $ledger;
    private ChartOfAccountsService $chartOfAccounts;

    public function __construct(LedgerService $ledger, ChartOfAccountsService $chartOfAccounts)
    {
        $this->ledger = $ledger;
        $this->chartOfAccounts = $chartOfAccounts;
    }

    public function postDonation(Donation $donation): ?JournalEntry
    {
        if ($donation->status !== 'completed') {
            return null;
        }

        $campaign = $donation->campaign;
        $this->ensureChartOfAccounts($campaign);

        if ($this->hasExistingEntry('donation', $donation->id, $campaign->id)) {
            return null;
        }

        $cashAccount = $this->chartOfAccounts->getCashAccountForChannel($campaign, $donation->channel ?? 'cash');
        $revenueAccount = $this->chartOfAccounts->getRevenueAccountForSource($campaign, $donation->source_type);
        $defaultFund = $this->chartOfAccounts->getDefaultFund($campaign);

        if (!$cashAccount || !$revenueAccount) {
            return null;
        }

        $amount = (float) $donation->amount;
        $fundId = $defaultFund?->id;

        return $this->ledger->post([
            'campaign_id' => $campaign->id,
            'date' => ($donation->donated_at ?? $donation->created_at)->toDateString(),
            'description' => 'Donation: ' . ($donation->donor_name ?? 'Anonymous') . ' via ' . ($donation->channel ?? 'cash'),
            'reference_type' => 'donation',
            'reference_id' => $donation->id,
            'metadata' => [
                'channel' => $donation->channel,
                'source_type' => $donation->source_type,
            ],
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'fund_id' => $fundId,
                    'description' => 'Cash received – ' . ($donation->channel ?? 'cash'),
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'fund_id' => $fundId,
                    'description' => 'Donation revenue',
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
        ]);
    }

    public function postExpenseApproval(Expense $expense): ?JournalEntry
    {
        if ($expense->status !== 'approved') {
            return null;
        }

        $campaign = $expense->campaign;
        $this->ensureChartOfAccounts($campaign);

        if ($this->hasExistingEntry('expense', $expense->id, $campaign->id)) {
            return null;
        }

        $expenseAccount = $this->chartOfAccounts->getExpenseAccountForCategory($campaign, $expense->category ?? 'other');
        $cashAccount = $this->chartOfAccounts->getCashAccountForPaymentMethod($campaign, $expense->payment_method ?? 'cash');
        $defaultFund = $this->chartOfAccounts->getDefaultFund($campaign);

        if (!$expenseAccount || !$cashAccount) {
            return null;
        }

        $amount = (float) $expense->amount;
        $fundId = $defaultFund?->id;

        return $this->ledger->post([
            'campaign_id' => $campaign->id,
            'date' => ($expense->expense_date ?? $expense->created_at)->toDateString(),
            'description' => 'Expense: ' . $expense->title,
            'reference_type' => 'expense',
            'reference_id' => $expense->id,
            'posted_by' => $expense->approved_by,
            'metadata' => [
                'category' => $expense->category,
                'payment_method' => $expense->payment_method,
                'budget_id' => $expense->budget_id,
            ],
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'fund_id' => $fundId,
                    'description' => $expense->title,
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cashAccount->id,
                    'fund_id' => $fundId,
                    'description' => 'Payment – ' . ($expense->payment_method ?? 'cash'),
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
        ]);
    }

    public function reverseExpense(Expense $expense, ?int $userId = null): ?JournalEntry
    {
        $entry = JournalEntry::where('reference_type', 'expense')
            ->where('reference_id', $expense->id)
            ->where('campaign_id', $expense->campaign_id)
            ->where('is_posted', true)
            ->where('is_reversed', false)
            ->first();

        if (!$entry) {
            return null;
        }

        return $this->ledger->reverse($entry, $userId);
    }

    public function reverseDonation(Donation $donation, ?int $userId = null): ?JournalEntry
    {
        $entry = JournalEntry::where('reference_type', 'donation')
            ->where('reference_id', $donation->id)
            ->where('campaign_id', $donation->campaign_id)
            ->where('is_posted', true)
            ->where('is_reversed', false)
            ->first();

        if (!$entry) {
            return null;
        }

        return $this->ledger->reverse($entry, $userId);
    }

    private function ensureChartOfAccounts(Campaign $campaign): void
    {
        if (!$this->chartOfAccounts->isSeeded($campaign)) {
            $this->chartOfAccounts->seedForCampaign($campaign);
        }
    }

    private function hasExistingEntry(string $type, int $id, int $campaignId): bool
    {
        return JournalEntry::where('reference_type', $type)
            ->where('reference_id', $id)
            ->where('campaign_id', $campaignId)
            ->where('is_posted', true)
            ->where('is_reversed', false)
            ->exists();
    }
}
