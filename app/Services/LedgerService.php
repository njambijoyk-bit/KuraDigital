<?php

namespace App\Services;

use App\Exceptions\UnbalancedEntryException;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Campaign;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Post a journal entry with balanced lines.
     *
     * @param array{
     *     campaign_id: int,
     *     date: string,
     *     description: string,
     *     reference_type?: string,
     *     reference_id?: int,
     *     posted_by?: int,
     *     metadata?: array,
     *     lines: array<array{account_id: int, fund_id?: int, description?: string, debit: float, credit: float}>
     * } $data
     */
    public function post(array $data): JournalEntry
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $debitTotal = array_sum(array_column($lines, 'debit'));
        $creditTotal = array_sum(array_column($lines, 'credit'));

        if (abs($debitTotal - $creditTotal) >= 0.01) {
            throw new UnbalancedEntryException($debitTotal, $creditTotal);
        }

        return DB::transaction(function () use ($data, $lines) {
            $data['entry_number'] = $this->nextEntryNumber($data['campaign_id']);
            $data['is_posted'] = true;

            $entry = JournalEntry::create($data);

            foreach ($lines as $line) {
                $line['journal_entry_id'] = $entry->id;
                JournalLine::create($line);
            }

            $this->updateBalances($entry, $lines);

            $entry->load('lines.account');

            return $entry;
        });
    }

    public function reverse(JournalEntry $entry, ?int $reversedByUserId = null): JournalEntry
    {
        if ($entry->is_reversed) {
            throw new \RuntimeException('Journal entry is already reversed.');
        }

        return DB::transaction(function () use ($entry, $reversedByUserId) {
            $reversalLines = [];
            foreach ($entry->lines as $line) {
                $reversalLines[] = [
                    'account_id' => $line->account_id,
                    'fund_id' => $line->fund_id,
                    'description' => 'Reversal: ' . ($line->description ?? ''),
                    'debit' => (float) $line->credit,
                    'credit' => (float) $line->debit,
                ];
            }

            $reversal = $this->post([
                'campaign_id' => $entry->campaign_id,
                'date' => now()->toDateString(),
                'description' => 'Reversal of ' . $entry->entry_number . ': ' . $entry->description,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'posted_by' => $reversedByUserId,
                'metadata' => ['reversed_entry' => $entry->entry_number],
                'lines' => $reversalLines,
            ]);

            $entry->update([
                'is_reversed' => true,
                'reversed_by_id' => $reversal->id,
            ]);

            return $reversal;
        });
    }

    public function verifyIntegrity(Campaign $campaign): array
    {
        $entries = JournalEntry::where('campaign_id', $campaign->id)
            ->where('is_posted', true)
            ->with('lines')
            ->get();

        $errors = [];
        foreach ($entries as $entry) {
            $debits = $entry->lines->sum('debit');
            $credits = $entry->lines->sum('credit');
            if (abs($debits - $credits) >= 0.01) {
                $errors[] = [
                    'entry_number' => $entry->entry_number,
                    'debit_total' => $debits,
                    'credit_total' => $credits,
                    'difference' => abs($debits - $credits),
                ];
            }
        }

        $totalDebits = JournalLine::whereHas('journalEntry', function ($q) use ($campaign) {
            $q->where('campaign_id', $campaign->id)->where('is_posted', true);
        })->sum('debit');

        $totalCredits = JournalLine::whereHas('journalEntry', function ($q) use ($campaign) {
            $q->where('campaign_id', $campaign->id)->where('is_posted', true);
        })->sum('credit');

        return [
            'total_entries' => $entries->count(),
            'unbalanced_entries' => count($errors),
            'errors' => $errors,
            'total_debits' => (float) $totalDebits,
            'total_credits' => (float) $totalCredits,
            'global_balanced' => abs($totalDebits - $totalCredits) < 0.01,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    public function getAccountBalance(Account $account, ?string $asOfDate = null): float
    {
        $query = JournalLine::where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true);
                if ($asOfDate) {
                    $q->where('date', '<=', $asOfDate);
                }
            });

        $debits = (float) (clone $query)->sum('debit');
        $credits = (float) (clone $query)->sum('credit');

        return $account->isDebitNormal()
            ? $debits - $credits
            : $credits - $debits;
    }

    public function getTrialBalance(Campaign $campaign, ?string $asOfDate = null): array
    {
        $accounts = Account::where('campaign_id', $campaign->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $trialBalance = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($accounts as $account) {
            $query = JournalLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($campaign, $asOfDate) {
                    $q->where('campaign_id', $campaign->id)->where('is_posted', true);
                    if ($asOfDate) {
                        $q->where('date', '<=', $asOfDate);
                    }
                });

            $debits = (float) (clone $query)->sum('debit');
            $credits = (float) (clone $query)->sum('credit');
            $balance = $debits - $credits;

            if (abs($balance) < 0.01) {
                continue;
            }

            $debitBalance = $balance > 0 ? $balance : 0;
            $creditBalance = $balance < 0 ? abs($balance) : 0;

            $totalDebits += $debitBalance;
            $totalCredits += $creditBalance;

            $trialBalance[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => round($debitBalance, 2),
                'credit' => round($creditBalance, 2),
            ];
        }

        return [
            'accounts' => $trialBalance,
            'total_debits' => round($totalDebits, 2),
            'total_credits' => round($totalCredits, 2),
            'balanced' => abs($totalDebits - $totalCredits) < 0.01,
            'as_of' => $asOfDate ?? now()->toDateString(),
        ];
    }

    private function nextEntryNumber(int $campaignId): string
    {
        $year = now()->format('Y');
        $lastEntry = JournalEntry::where('campaign_id', $campaignId)
            ->where('entry_number', 'like', "JE-{$year}-%")
            ->orderByDesc('entry_number')
            ->first();

        if ($lastEntry) {
            $lastNum = (int) substr($lastEntry->entry_number, strrpos($lastEntry->entry_number, '-') + 1);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('JE-%s-%04d', $year, $nextNum);
    }

    private function updateBalances(JournalEntry $entry, array $lines): void
    {
        $period = $entry->date->format('Y-m');

        foreach ($lines as $line) {
            $fundId = $line['fund_id'] ?? null;

            AccountBalance::updateOrCreate(
                [
                    'account_id' => $line['account_id'],
                    'campaign_id' => $entry->campaign_id,
                    'fund_id' => $fundId,
                    'period' => $period,
                ],
                []
            );

            $balance = AccountBalance::where('account_id', $line['account_id'])
                ->where('campaign_id', $entry->campaign_id)
                ->where('fund_id', $fundId)
                ->where('period', $period)
                ->first();

            $balance->debit_total = (float) $balance->debit_total + (float) $line['debit'];
            $balance->credit_total = (float) $balance->credit_total + (float) $line['credit'];

            $account = Account::find($line['account_id']);
            if ($account->isDebitNormal()) {
                $balance->balance = $balance->debit_total - $balance->credit_total;
            } else {
                $balance->balance = $balance->credit_total - $balance->debit_total;
            }

            $balance->save();
        }
    }
}
