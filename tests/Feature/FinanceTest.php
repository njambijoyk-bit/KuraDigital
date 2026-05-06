<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Donation;
use App\Models\Expense;
use App\Models\MpesaTransaction;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $financeDirector;
    private User $financeOfficer;
    private User $auditor;
    private User $volunteer;
    private User $outsider;
    private Campaign $campaign;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->site = Site::create([
            'slug' => 'finance-test',
            'candidate_name' => 'Finance Test',
            'is_active' => true,
        ]);

        $this->campaign = Campaign::create([
            'name' => 'Finance Campaign',
            'slug' => 'finance-campaign',
            'site_id' => $this->site->id,
            'level' => 'county',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create();
        $this->financeDirector = User::factory()->create();
        $this->financeOfficer = User::factory()->create();
        $this->auditor = User::factory()->create();
        $this->volunteer = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->createMembership($this->owner, 'campaign-owner');
        $this->createMembership($this->financeDirector, 'finance-director');
        $this->createMembership($this->financeOfficer, 'finance-officer');
        $this->createMembership($this->auditor, 'auditor');
        $this->createMembership($this->volunteer, 'volunteer');
    }

    private function createMembership(User $user, string $role, array $extra = []): CampaignMember
    {
        return CampaignMember::create(array_merge([
            'user_id' => $user->id,
            'campaign_id' => $this->campaign->id,
            'role' => $role,
            'is_active' => true,
            'visibility_scope' => 'own_campaign',
        ], $extra));
    }

    // =====================================================================
    // RBAC — Budgets
    // =====================================================================

    public function test_finance_director_can_create_budget(): void
    {
        $response = $this->actingAs($this->financeDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/budgets",
            ['name' => 'Media Budget', 'category' => 'media', 'allocated_amount' => 500000]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('budgets', ['name' => 'Media Budget', 'campaign_id' => $this->campaign->id]);
    }

    public function test_finance_officer_cannot_create_budget(): void
    {
        $response = $this->actingAs($this->financeOfficer)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/budgets",
            ['name' => 'Test', 'category' => 'operations', 'allocated_amount' => 100]
        );

        $response->assertStatus(403);
    }

    public function test_volunteer_cannot_view_budgets(): void
    {
        $response = $this->actingAs($this->volunteer)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/budgets"
        );

        $response->assertStatus(403);
    }

    public function test_finance_director_can_view_budgets(): void
    {
        Budget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeDirector->id,
            'name' => 'Ops Budget',
            'category' => 'operations',
            'allocated_amount' => 200000,
        ]);

        $response = $this->actingAs($this->financeDirector)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/budgets"
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Ops Budget']);
    }

    public function test_finance_director_can_update_budget(): void
    {
        $budget = Budget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeDirector->id,
            'name' => 'Old Name',
            'category' => 'events',
            'allocated_amount' => 100000,
        ]);

        $response = $this->actingAs($this->financeDirector)->putJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/budgets/{$budget->id}",
            ['name' => 'New Name', 'allocated_amount' => 200000]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'name' => 'New Name']);
    }

    public function test_finance_director_can_delete_budget(): void
    {
        $budget = Budget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeDirector->id,
            'name' => 'To Delete',
            'category' => 'other',
            'allocated_amount' => 50000,
        ]);

        $response = $this->actingAs($this->financeDirector)->deleteJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/budgets/{$budget->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    // =====================================================================
    // RBAC — Expenses
    // =====================================================================

    public function test_finance_officer_can_log_expense(): void
    {
        $response = $this->actingAs($this->financeOfficer)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses",
            [
                'title' => 'Fuel for rallies',
                'amount' => 15000,
                'category' => 'logistics',
                'expense_date' => '2026-05-01',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', ['title' => 'Fuel for rallies', 'status' => 'pending']);
    }

    public function test_volunteer_cannot_log_expense(): void
    {
        $response = $this->actingAs($this->volunteer)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses",
            ['title' => 'Test', 'amount' => 100, 'category' => 'other', 'expense_date' => '2026-05-01']
        );

        $response->assertStatus(403);
    }

    public function test_finance_director_can_approve_expense(): void
    {
        $expense = Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Pending Expense',
            'amount' => 5000,
            'category' => 'operations',
            'expense_date' => '2026-05-01',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->financeDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/{$expense->id}/approve"
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'status' => 'approved']);
    }

    public function test_finance_officer_cannot_approve_expense(): void
    {
        $expense = Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Pending',
            'amount' => 1000,
            'category' => 'other',
            'expense_date' => '2026-05-01',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->financeOfficer)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/{$expense->id}/approve"
        );

        $response->assertStatus(403);
    }

    public function test_finance_director_can_reject_expense(): void
    {
        $expense = Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Questionable Expense',
            'amount' => 50000,
            'category' => 'other',
            'expense_date' => '2026-05-01',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->financeDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/{$expense->id}/reject",
            ['rejection_reason' => 'No receipt provided']
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'status' => 'rejected', 'rejection_reason' => 'No receipt provided']);
    }

    public function test_cannot_approve_already_approved_expense(): void
    {
        $expense = Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Approved Already',
            'amount' => 1000,
            'category' => 'other',
            'expense_date' => '2026-05-01',
            'status' => 'approved',
            'approved_by' => $this->financeDirector->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->financeDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/{$expense->id}/approve"
        );

        $response->assertStatus(422);
    }

    public function test_cannot_edit_approved_expense(): void
    {
        $expense = Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Approved',
            'amount' => 1000,
            'category' => 'other',
            'expense_date' => '2026-05-01',
            'status' => 'approved',
            'approved_by' => $this->financeDirector->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->financeDirector)->putJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/{$expense->id}",
            ['title' => 'Modified']
        );

        $response->assertStatus(422);
    }

    public function test_cannot_delete_approved_expense(): void
    {
        $expense = Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Approved',
            'amount' => 1000,
            'category' => 'other',
            'expense_date' => '2026-05-01',
            'status' => 'approved',
            'approved_by' => $this->financeDirector->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->financeDirector)->deleteJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/{$expense->id}"
        );

        $response->assertStatus(422);
    }

    public function test_approval_recalculates_budget_spent(): void
    {
        $budget = Budget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeDirector->id,
            'name' => 'Test Budget',
            'category' => 'field',
            'allocated_amount' => 100000,
            'spent_amount' => 0,
        ]);

        $expense = Expense::create([
            'campaign_id' => $this->campaign->id,
            'budget_id' => $budget->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Field Trip',
            'amount' => 25000,
            'category' => 'field',
            'expense_date' => '2026-05-01',
            'status' => 'pending',
        ]);

        $this->actingAs($this->financeDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/{$expense->id}/approve"
        );

        $budget->refresh();
        $this->assertEquals(25000, (float) $budget->spent_amount);
    }

    // =====================================================================
    // RBAC — Donations
    // =====================================================================

    public function test_finance_director_can_record_donation(): void
    {
        $response = $this->actingAs($this->financeDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/donations",
            [
                'donor_name' => 'John Doe',
                'amount' => 10000,
                'channel' => 'cash',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('donations', ['donor_name' => 'John Doe', 'amount' => 10000]);
    }

    public function test_volunteer_cannot_view_donations(): void
    {
        $response = $this->actingAs($this->volunteer)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/donations"
        );

        $response->assertStatus(403);
    }

    public function test_auditor_can_view_donations(): void
    {
        Donation::create([
            'campaign_id' => $this->campaign->id,
            'donor_name' => 'Jane',
            'amount' => 5000,
            'channel' => 'mpesa',
            'status' => 'completed',
            'donated_at' => now(),
        ]);

        $response = $this->actingAs($this->auditor)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/donations"
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['donor_name' => 'Jane']);
    }

    public function test_finance_director_can_view_donation_detail(): void
    {
        $donation = Donation::create([
            'campaign_id' => $this->campaign->id,
            'donor_name' => 'Detail Test',
            'amount' => 3000,
            'channel' => 'bank_transfer',
            'status' => 'completed',
            'donated_at' => now(),
        ]);

        $response = $this->actingAs($this->financeDirector)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/donations/{$donation->id}"
        );

        $response->assertStatus(200);
        $response->assertJsonFragment(['donor_name' => 'Detail Test']);
    }

    // =====================================================================
    // Finance Summary
    // =====================================================================

    public function test_finance_summary_aggregates_correctly(): void
    {
        Budget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeDirector->id,
            'name' => 'Budget A',
            'category' => 'operations',
            'allocated_amount' => 100000,
            'spent_amount' => 30000,
        ]);

        Budget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeDirector->id,
            'name' => 'Budget B',
            'category' => 'media',
            'allocated_amount' => 50000,
            'spent_amount' => 10000,
        ]);

        Donation::create([
            'campaign_id' => $this->campaign->id,
            'donor_name' => 'Donor A',
            'amount' => 25000,
            'channel' => 'mpesa',
            'status' => 'completed',
            'donated_at' => now(),
        ]);

        $response = $this->actingAs($this->financeDirector)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/summary"
        );

        $response->assertStatus(200);
        $data = $response->json('summary');
        $this->assertEquals(150000, $data['total_budget']);
        $this->assertEquals(40000, $data['total_spent']);
        $this->assertEquals(110000, $data['budget_remaining']);
        $this->assertEquals(25000, $data['total_donations']);
    }

    // =====================================================================
    // Export
    // =====================================================================

    public function test_finance_director_can_export_expenses(): void
    {
        Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->financeOfficer->id,
            'title' => 'Export Test',
            'amount' => 1000,
            'category' => 'other',
            'expense_date' => '2026-05-01',
            'status' => 'approved',
            'approved_by' => $this->financeDirector->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->financeDirector)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/export"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['expenses', 'summary']);
    }

    public function test_finance_officer_cannot_export_expenses(): void
    {
        $response = $this->actingAs($this->financeOfficer)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses/export"
        );

        $response->assertStatus(403);
    }

    // =====================================================================
    // M-Pesa Webhooks
    // =====================================================================

    public function test_stk_callback_creates_donation(): void
    {
        $transaction = MpesaTransaction::create([
            'campaign_id' => $this->campaign->id,
            'transaction_type' => 'stk_push',
            'merchant_request_id' => 'MR123',
            'checkout_request_id' => 'CR123',
            'amount' => 1000,
            'phone_number' => '254712345678',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/webhooks/mpesa/stk-callback', [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR123',
                    'CheckoutRequestID' => 'CR123',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 1000],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QHK7B2C5LP'],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                            ['Name' => 'TransactionDate', 'Value' => 20260503120000],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);

        $transaction->refresh();
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals('QHK7B2C5LP', $transaction->receipt_number);

        $this->assertDatabaseHas('donations', [
            'campaign_id' => $this->campaign->id,
            'amount' => 1000,
            'mpesa_receipt' => 'QHK7B2C5LP',
            'channel' => 'mpesa',
            'status' => 'completed',
        ]);
    }

    public function test_stk_callback_handles_cancellation(): void
    {
        $transaction = MpesaTransaction::create([
            'campaign_id' => $this->campaign->id,
            'transaction_type' => 'stk_push',
            'merchant_request_id' => 'MR456',
            'checkout_request_id' => 'CR456',
            'amount' => 500,
            'phone_number' => '254712345678',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/webhooks/mpesa/stk-callback', [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MR456',
                    'CheckoutRequestID' => 'CR456',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user.',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $transaction->refresh();
        $this->assertEquals('cancelled', $transaction->status);
        $this->assertDatabaseMissing('donations', ['campaign_id' => $this->campaign->id, 'amount' => 500]);
    }

    public function test_c2b_confirmation_creates_donation(): void
    {
        $response = $this->postJson('/api/v1/webhooks/mpesa/c2b-confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'OGF7K1B2LP',
            'TransAmount' => 2000,
            'MSISDN' => '254700123456',
            'BillRefNumber' => 'KURA-' . $this->campaign->id,
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('donations', [
            'campaign_id' => $this->campaign->id,
            'donor_name' => 'John Doe',
            'amount' => 2000,
            'mpesa_receipt' => 'OGF7K1B2LP',
            'channel' => 'mpesa',
        ]);
        $this->assertDatabaseHas('mpesa_transactions', [
            'receipt_number' => 'OGF7K1B2LP',
            'transaction_type' => 'c2b',
            'status' => 'completed',
        ]);
    }

    public function test_c2b_validation_accepts(): void
    {
        $response = $this->postJson('/api/v1/webhooks/mpesa/c2b-validation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TEST123',
            'TransAmount' => 100,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ResultCode' => 0]);
    }

    // =====================================================================
    // Campaign isolation
    // =====================================================================

    public function test_non_member_cannot_access_finance(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/budgets"
        );
        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_expenses(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/expenses"
        );
        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_donations(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/donations"
        );
        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_summary(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/summary"
        );
        $response->assertStatus(403);
    }

    // =====================================================================
    // M-Pesa not configured
    // =====================================================================

    public function test_stk_push_returns_503_when_not_configured(): void
    {
        $response = $this->actingAs($this->financeDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/finance/mpesa/stk-push",
            ['phone_number' => '0712345678', 'amount' => 100]
        );

        $response->assertStatus(503);
    }
}
