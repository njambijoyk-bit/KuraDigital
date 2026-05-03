<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Donation;
use App\Models\Expense;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\Report;
use App\Models\Site;
use App\Models\TallyResult;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;
    private User $dataAnalyst;
    private User $strategyDirector;
    private User $volunteer;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $site = Site::create([
            'slug' => 'reports-test',
            'candidate_name' => 'Reports Test',
            'is_active' => true,
        ]);
        $this->campaign = Campaign::create([
            'name' => 'Reports Test Campaign',
            'slug' => 'reports-test-campaign',
            'site_id' => $site->id,
            'level' => 'county',
            'is_active' => true,
        ]);

        $this->dataAnalyst = User::factory()->create();
        $this->dataAnalyst->assignRole('data-analyst');
        $this->createMembership($this->dataAnalyst, 'data-analyst');

        $this->strategyDirector = User::factory()->create();
        $this->strategyDirector->assignRole('strategy-director');
        $this->createMembership($this->strategyDirector, 'strategy-director');

        $this->volunteer = User::factory()->create();
        $this->volunteer->assignRole('volunteer');
        $this->createMembership($this->volunteer, 'volunteer');

        $this->outsider = User::factory()->create();
        $this->outsider->assignRole('volunteer');
    }

    private function createMembership(User $user, string $role): CampaignMember
    {
        return CampaignMember::create([
            'user_id' => $user->id,
            'campaign_id' => $this->campaign->id,
            'role' => $role,
            'is_active' => true,
            'visibility_scope' => 'own_campaign',
        ]);
    }

    // =====================================================================
    // Reports — Generate
    // =====================================================================

    public function test_data_analyst_can_generate_report(): void
    {
        $response = $this->actingAs($this->dataAnalyst)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/reports/generate", [
                'type' => 'voter_summary',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'voter_summary']);
        $this->assertDatabaseHas('reports', [
            'campaign_id' => $this->campaign->id,
            'type' => 'voter_summary',
            'created_by' => $this->dataAnalyst->id,
        ]);
    }

    public function test_volunteer_cannot_generate_report(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/reports/generate", [
                'type' => 'voter_summary',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_generate_campaign_overview(): void
    {
        $response = $this->actingAs($this->dataAnalyst)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/reports/generate", [
                'type' => 'campaign_overview',
                'title' => 'My Overview',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertArrayHasKey('campaign', $data);
        $this->assertArrayHasKey('team', $data);
        $this->assertArrayHasKey('voters', $data);
        $this->assertArrayHasKey('finance', $data);
    }

    public function test_can_generate_finance_report(): void
    {
        Budget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'name' => 'Transport Budget',
            'category' => 'transport',
            'allocated_amount' => 100000,
            'spent_amount' => 30000,
        ]);

        Expense::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'title' => 'Fuel',
            'amount' => 5000,
            'category' => 'transport',
            'expense_date' => now()->toDateString(),
            'status' => 'approved',
            'approved_by' => $this->strategyDirector->id,
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/reports/generate", [
                'type' => 'finance',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals(100000, $data['total_budget']);
        $this->assertEquals(30000, $data['total_spent']);
    }

    public function test_can_generate_election_day_report(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'name' => 'Station A',
            'code' => 'SA-001',
            'ward' => 'Westlands',
            'constituency' => 'Westlands',
            'county' => 'Nairobi',
            'registered_voters' => 1000,
            'status' => 'open',
        ]);

        TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->dataAnalyst->id,
            'candidate_name' => 'Candidate A',
            'party' => 'Party X',
            'votes' => 500,
            'total_votes_cast' => 800,
            'status' => 'verified',
        ]);

        TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->dataAnalyst->id,
            'candidate_name' => 'Candidate B',
            'party' => 'Party Y',
            'votes' => 300,
            'total_votes_cast' => 800,
            'status' => 'verified',
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/reports/generate", [
                'type' => 'election_day',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals(1, $data['stations']['total']);
        $this->assertEquals(1, $data['stations']['reported']);
        $this->assertEquals(800, $data['votes']['total_cast']);
        $this->assertCount(2, $data['candidates']);
    }

    // =====================================================================
    // Reports — List & View
    // =====================================================================

    public function test_data_analyst_can_list_reports(): void
    {
        Report::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'title' => 'Test Report',
            'type' => 'voter_summary',
            'data' => ['total' => 100],
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/reports");

        $response->assertStatus(200);
    }

    public function test_data_analyst_can_view_report(): void
    {
        $report = Report::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'title' => 'Test Report',
            'type' => 'voter_summary',
            'data' => ['total' => 100],
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/reports/{$report->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'Test Report']);
    }

    public function test_data_analyst_can_delete_report(): void
    {
        $report = Report::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'title' => 'To Delete',
            'type' => 'voter_summary',
            'data' => [],
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->deleteJson("/api/v1/campaigns/{$this->campaign->id}/reports/{$report->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }

    // =====================================================================
    // Reports — Export
    // =====================================================================

    public function test_data_analyst_can_export_csv(): void
    {
        $response = $this->actingAs($this->dataAnalyst)
            ->get("/api/v1/campaigns/{$this->campaign->id}/reports/export?type=voter_summary");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Total Voters', $response->getContent());
    }

    public function test_volunteer_cannot_export(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->get("/api/v1/campaigns/{$this->campaign->id}/reports/export?type=voter_summary");

        $response->assertStatus(403);
    }

    // =====================================================================
    // Reports — Validation
    // =====================================================================

    public function test_generate_validates_type(): void
    {
        $response = $this->actingAs($this->dataAnalyst)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/reports/generate", [
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422);
    }

    public function test_filter_reports_by_type(): void
    {
        Report::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'title' => 'Voter Report',
            'type' => 'voter_summary',
            'data' => [],
        ]);
        Report::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->dataAnalyst->id,
            'title' => 'Finance Report',
            'type' => 'finance',
            'data' => [],
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/reports?type=voter_summary");

        $response->assertStatus(200);
    }

    // =====================================================================
    // Analytics — Overview
    // =====================================================================

    public function test_strategy_director_can_view_overview(): void
    {
        $response = $this->actingAs($this->strategyDirector)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/analytics/overview");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('team_members', $data);
        $this->assertArrayHasKey('voters', $data);
        $this->assertArrayHasKey('total_budget', $data);
        $this->assertArrayHasKey('total_donations', $data);
    }

    public function test_volunteer_cannot_view_analytics(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/analytics/overview");

        $response->assertStatus(403);
    }

    // =====================================================================
    // Analytics — Voter Growth
    // =====================================================================

    public function test_can_view_voter_growth(): void
    {
        Voter::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Jane Doe',
            'ward' => 'Westlands',
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/analytics/voter-growth?days=30");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('period_count', $data);
        $this->assertArrayHasKey('daily', $data);
        $this->assertEquals(1, $data['total']);
    }

    // =====================================================================
    // Analytics — Field Activity
    // =====================================================================

    public function test_can_view_field_activity(): void
    {
        $response = $this->actingAs($this->dataAnalyst)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/analytics/field-activity?days=30");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('check_ins_daily', $data);
        $this->assertArrayHasKey('reports_daily', $data);
        $this->assertArrayHasKey('top_agents', $data);
    }

    // =====================================================================
    // Analytics — Finance Trends
    // =====================================================================

    public function test_can_view_finance_trends(): void
    {
        $response = $this->actingAs($this->dataAnalyst)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/analytics/finance-trends?days=30");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('expenses_daily', $data);
        $this->assertArrayHasKey('donations_daily', $data);
        $this->assertArrayHasKey('budget_utilization', $data);
    }

    // =====================================================================
    // Analytics — Geographic
    // =====================================================================

    public function test_can_view_geographic_breakdown(): void
    {
        Voter::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Test Voter',
            'ward' => 'Kibera',
            'constituency' => 'Langata',
        ]);

        $response = $this->actingAs($this->dataAnalyst)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/analytics/geographic");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('voters_by_ward', $data);
        $this->assertArrayHasKey('voters_by_constituency', $data);
        $this->assertEquals(1, $data['voters_by_ward']['Kibera'] ?? 0);
    }

    // =====================================================================
    // Campaign Isolation
    // =====================================================================

    public function test_non_member_cannot_access_reports(): void
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/reports");

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_analytics(): void
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/analytics/overview");

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_generate_report(): void
    {
        $response = $this->actingAs($this->outsider)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/reports/generate", [
                'type' => 'voter_summary',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_export(): void
    {
        $response = $this->actingAs($this->outsider)
            ->get("/api/v1/campaigns/{$this->campaign->id}/reports/export?type=voter_summary");

        $response->assertStatus(403);
    }
}
