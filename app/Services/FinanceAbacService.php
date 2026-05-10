<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Campaign;
use App\Models\CampaignFinanceSetting;
use App\Models\Donation;
use App\Models\Expense;
use App\Models\User;

class FinanceAbacService
{
    public function canApproveExpense(User $user, Expense $expense, Campaign $campaign): AbacResult
    {
        $settings = $this->getSettings($campaign);

        // Segregation of duties
        if ($settings && $settings->require_segregation_of_duties && $expense->created_by === $user->id) {
            return AbacResult::deny('Cannot approve your own expense — segregation of duties policy.');
        }

        // Amount-based approval limit
        $limit = $this->getApprovalLimit($user, $campaign);
        if ($limit !== null && $expense->amount > $limit) {
            return AbacResult::deny(
                "Amount KES " . number_format($expense->amount, 2) . " exceeds your approval limit of KES " . number_format($limit, 2) . ".",
                $this->suggestRequiredRole($expense->amount, $settings)
            );
        }

        // Geographic scope
        $membership = $user->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($expense)) {
            return AbacResult::deny('Expense is outside your geographic scope.');
        }

        // Budget overflow check
        if ($expense->budget_id) {
            $budget = $expense->budget;
            if ($budget) {
                $newSpent = $budget->spent_amount + $expense->amount;
                if ($newSpent > $budget->allocated_amount) {
                    $over = $newSpent - $budget->allocated_amount;
                    return AbacResult::warn("Approving would exceed budget by KES " . number_format($over, 2) . ".");
                }
            }
        }

        // Spending cap compliance
        $capResult = $this->checkSpendingCap($campaign, $expense->amount);
        if (!$capResult->allowed) {
            return $capResult;
        }

        // Time-based cutoff
        if ($this->isPastCutoff($campaign)) {
            return AbacResult::deny('Expense approval is closed — past election date cutoff.');
        }

        return AbacResult::allow();
    }

    public function canLogExpense(User $user, Campaign $campaign): AbacResult
    {
        if ($this->isPastCutoff($campaign)) {
            return AbacResult::deny('Expense logging is closed for this election period.');
        }

        return AbacResult::allow();
    }

    public function canAcceptDonation(Donation $donation, Campaign $campaign): AbacResult
    {
        $settings = $this->getSettings($campaign);
        if (!$settings) {
            return AbacResult::allow();
        }

        // Source restrictions
        if ($donation->source_type === 'foreign') {
            return AbacResult::deny('Foreign donations are not permitted under Kenya election law.');
        }

        // Individual donor cap
        if (!$donation->is_anonymous && $donation->donor_phone && $settings->individual_donation_cap) {
            $totalFromDonor = Donation::where('campaign_id', $campaign->id)
                ->where('donor_phone_index', app(FinanceEncryptionService::class)->blindIndex($donation->donor_phone))
                ->where('status', 'completed')
                ->sum('amount');

            $projected = $totalFromDonor + $donation->amount;
            if ($projected > $settings->individual_donation_cap) {
                return AbacResult::deny(
                    "Total from this donor would be KES " . number_format($projected, 2)
                    . ", exceeding individual cap of KES " . number_format($settings->individual_donation_cap, 2) . "."
                );
            }
        }

        // Corporate donation cap
        if ($donation->source_type === 'corporate' && $settings->corporate_donation_cap) {
            if ($donation->amount > $settings->corporate_donation_cap) {
                return AbacResult::deny(
                    "Corporate donation of KES " . number_format($donation->amount, 2)
                    . " exceeds corporate cap of KES " . number_format($settings->corporate_donation_cap, 2) . "."
                );
            }
        }

        // Disclosure threshold
        if ($settings->disclosure_threshold && $donation->amount >= $settings->disclosure_threshold) {
            return AbacResult::requireDisclosure(
                "Donation of KES " . number_format($donation->amount, 2)
                . " requires KYC disclosure (threshold: KES " . number_format($settings->disclosure_threshold, 2) . ")."
            );
        }

        return AbacResult::allow();
    }

    public function checkSpendingCap(Campaign $campaign, float $additionalAmount = 0): AbacResult
    {
        $settings = $this->getSettings($campaign);
        if (!$settings || !$settings->spending_cap) {
            return AbacResult::allow();
        }

        $totalSpent = Expense::where('campaign_id', $campaign->id)
            ->where('status', 'approved')
            ->sum('amount');

        $projected = $totalSpent + $additionalAmount;

        if ($projected > $settings->spending_cap) {
            return AbacResult::deny(
                "Total spending would be KES " . number_format($projected, 2)
                . ", exceeding the legal spending cap of KES " . number_format($settings->spending_cap, 2) . "."
            );
        }

        return AbacResult::allow();
    }

    public function getApprovalLimit(User $user, Campaign $campaign): ?float
    {
        $settings = $this->getSettings($campaign);
        if (!$settings || !$settings->approval_limits) {
            return null;
        }

        $role = $user->campaignRole($campaign);
        if (!$role) {
            return 0;
        }

        return $settings->getApprovalLimitForRole($role);
    }

    public function isPastCutoff(Campaign $campaign): bool
    {
        $settings = $this->getSettings($campaign);
        if (!$settings || !$settings->election_date) {
            return false;
        }

        return now()->startOfDay()->greaterThan($settings->election_date);
    }

    public function getSpendingCapUsage(Campaign $campaign): ?array
    {
        $settings = $this->getSettings($campaign);
        if (!$settings || !$settings->spending_cap) {
            return null;
        }

        $totalSpent = Expense::where('campaign_id', $campaign->id)
            ->where('status', 'approved')
            ->sum('amount');

        $cap = (float) $settings->spending_cap;
        $percent = $cap > 0 ? round(($totalSpent / $cap) * 100, 1) : 0;

        return [
            'spent' => $totalSpent,
            'cap' => $cap,
            'remaining' => $cap - $totalSpent,
            'percent' => $percent,
            'status' => $percent >= $settings->critical_at_percent ? 'critical'
                : ($percent >= $settings->alert_at_percent ? 'warning' : 'healthy'),
        ];
    }

    private function getSettings(Campaign $campaign): ?CampaignFinanceSetting
    {
        return CampaignFinanceSetting::where('campaign_id', $campaign->id)->first();
    }

    private function suggestRequiredRole(float $amount, ?CampaignFinanceSetting $settings): ?string
    {
        if (!$settings || !$settings->approval_limits) {
            return 'campaign-owner';
        }

        foreach ($settings->approval_limits as $role => $limit) {
            if ($amount <= $limit) {
                return $role;
            }
        }

        return 'campaign-owner';
    }
}
