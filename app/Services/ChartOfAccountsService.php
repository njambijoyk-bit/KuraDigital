<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Fund;

class ChartOfAccountsService
{
    private const DEFAULT_ACCOUNTS = [
        // Assets
        ['code' => '1000', 'name' => 'Cash & Bank', 'type' => 'asset', 'parent' => null],
        ['code' => '1100', 'name' => 'Cash – M-Pesa', 'type' => 'asset', 'parent' => '1000'],
        ['code' => '1110', 'name' => 'Cash – Bank Transfer', 'type' => 'asset', 'parent' => '1000'],
        ['code' => '1120', 'name' => 'Cash – On Hand', 'type' => 'asset', 'parent' => '1000'],
        ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent' => null],
        ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'parent' => null],

        // Liabilities
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent' => null],
        ['code' => '2100', 'name' => 'Accrued Expenses', 'type' => 'liability', 'parent' => null],

        // Equity
        ['code' => '3000', 'name' => 'Campaign Fund Balance', 'type' => 'equity', 'parent' => null],
        ['code' => '3100', 'name' => 'Restricted Funds', 'type' => 'equity', 'parent' => null],

        // Revenue
        ['code' => '4000', 'name' => 'Donation Revenue', 'type' => 'revenue', 'parent' => null],
        ['code' => '4100', 'name' => 'Individual Donations', 'type' => 'revenue', 'parent' => '4000'],
        ['code' => '4110', 'name' => 'Corporate Donations', 'type' => 'revenue', 'parent' => '4000'],
        ['code' => '4120', 'name' => 'Fundraiser Proceeds', 'type' => 'revenue', 'parent' => '4000'],
        ['code' => '4130', 'name' => 'Party Contributions', 'type' => 'revenue', 'parent' => '4000'],
        ['code' => '4200', 'name' => 'Other Income', 'type' => 'revenue', 'parent' => null],

        // Expenses
        ['code' => '5000', 'name' => 'Campaign Expenses', 'type' => 'expense', 'parent' => null],
        ['code' => '5100', 'name' => 'Operations', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5200', 'name' => 'Media & Advertising', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5300', 'name' => 'Events & Rallies', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5400', 'name' => 'Field Operations', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5500', 'name' => 'Personnel', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5600', 'name' => 'Logistics & Transport', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5700', 'name' => 'Other Expenses', 'type' => 'expense', 'parent' => '5000'],
    ];

    private const CHANNEL_ACCOUNT_MAP = [
        'mpesa' => '1100',
        'bank_transfer' => '1110',
        'cash' => '1120',
        'online' => '1110',
        'cheque' => '1110',
    ];

    private const SOURCE_ACCOUNT_MAP = [
        'individual' => '4100',
        'corporate' => '4110',
        'fundraiser' => '4120',
        'party' => '4130',
    ];

    private const CATEGORY_ACCOUNT_MAP = [
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

    public function seedForCampaign(Campaign $campaign): void
    {
        if (Account::where('campaign_id', $campaign->id)->exists()) {
            return;
        }

        $parentMap = [];

        foreach (self::DEFAULT_ACCOUNTS as $acct) {
            $parentId = null;
            if ($acct['parent'] && isset($parentMap[$acct['parent']])) {
                $parentId = $parentMap[$acct['parent']];
            }

            $account = Account::create([
                'campaign_id' => $campaign->id,
                'code' => $acct['code'],
                'name' => $acct['name'],
                'type' => $acct['type'],
                'parent_id' => $parentId,
                'is_system' => true,
                'is_active' => true,
            ]);

            $parentMap[$acct['code']] = $account->id;
        }

        Fund::create([
            'campaign_id' => $campaign->id,
            'name' => 'General Fund',
            'code' => 'GEN',
            'description' => 'Unrestricted general campaign fund',
            'is_restricted' => false,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function isSeeded(Campaign $campaign): bool
    {
        return Account::where('campaign_id', $campaign->id)->exists();
    }

    public function getAccountByCode(Campaign $campaign, string $code): ?Account
    {
        return Account::where('campaign_id', $campaign->id)
            ->where('code', $code)
            ->first();
    }

    public function getCashAccountForChannel(Campaign $campaign, string $channel): ?Account
    {
        $code = self::CHANNEL_ACCOUNT_MAP[$channel] ?? '1120';
        return $this->getAccountByCode($campaign, $code);
    }

    public function getRevenueAccountForSource(Campaign $campaign, ?string $sourceType): ?Account
    {
        $code = self::SOURCE_ACCOUNT_MAP[$sourceType ?? 'individual'] ?? '4000';
        return $this->getAccountByCode($campaign, $code);
    }

    public function getExpenseAccountForCategory(Campaign $campaign, string $category): ?Account
    {
        $code = self::CATEGORY_ACCOUNT_MAP[$category] ?? '5700';
        return $this->getAccountByCode($campaign, $code);
    }

    public function getCashAccountForPaymentMethod(Campaign $campaign, string $paymentMethod): ?Account
    {
        $code = self::CHANNEL_ACCOUNT_MAP[$paymentMethod] ?? '1120';
        return $this->getAccountByCode($campaign, $code);
    }

    public function getDefaultFund(Campaign $campaign): ?Fund
    {
        return Fund::where('campaign_id', $campaign->id)
            ->where('is_default', true)
            ->first();
    }
}
