<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Expense;
use App\Services\AutoPostingService;
use App\Services\ChartOfAccountsService;
use Illuminate\Console\Command;

class BackfillLedger extends Command
{
    protected $signature = 'ledger:backfill {campaign_id? : Campaign ID to backfill (omit for all)}';
    protected $description = 'Backfill journal entries from existing expenses and donations';

    public function handle(AutoPostingService $autoPosting, ChartOfAccountsService $chartOfAccounts): int
    {
        $campaignId = $this->argument('campaign_id');

        $campaigns = $campaignId
            ? Campaign::where('id', $campaignId)->get()
            : Campaign::all();

        if ($campaigns->isEmpty()) {
            $this->error('No campaigns found.');
            return 1;
        }

        foreach ($campaigns as $campaign) {
            $this->info("Processing campaign: {$campaign->name} (ID: {$campaign->id})");

            $chartOfAccounts->seedForCampaign($campaign);
            $this->info('  ✓ Chart of accounts seeded');

            $donations = Donation::where('campaign_id', $campaign->id)
                ->where('status', 'completed')
                ->get();

            $donationCount = 0;
            foreach ($donations as $donation) {
                $entry = $autoPosting->postDonation($donation);
                if ($entry) {
                    $donationCount++;
                }
            }
            $this->info("  ✓ {$donationCount} donation(s) posted (of {$donations->count()} completed)");

            $expenses = Expense::where('campaign_id', $campaign->id)
                ->where('status', 'approved')
                ->get();

            $expenseCount = 0;
            foreach ($expenses as $expense) {
                $entry = $autoPosting->postExpenseApproval($expense);
                if ($entry) {
                    $expenseCount++;
                }
            }
            $this->info("  ✓ {$expenseCount} expense(s) posted (of {$expenses->count()} approved)");
        }

        $this->info('Backfill complete.');
        return 0;
    }
}
