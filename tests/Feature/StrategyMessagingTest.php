<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\MessageCampaign;
use App\Models\MessageTemplate;
use App\Models\Poll;
use App\Models\Site;
use App\Models\StrategyNote;
use App\Models\User;
use App\Models\WardTarget;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyMessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $strategyDirector;
    private User $commsDirector;
    private User $dataAnalyst;
    private User $smsOperator;
    private User $volunteer;
    private User $outsider;
    private Campaign $campaign;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->site = Site::create([
            'slug' => 'strat-test',
            'candidate_name' => 'Strategy Test',
            'is_active' => true,
        ]);

        $this->campaign = Campaign::create([
            'name' => 'Strategy Campaign',
            'slug' => 'strategy-campaign',
            'site_id' => $this->site->id,
            'level' => 'county',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create();
        $this->strategyDirector = User::factory()->create(['clearance_level' => 'top_secret']);
        $this->commsDirector = User::factory()->create();
        $this->dataAnalyst = User::factory()->create(['clearance_level' => 'internal']);
        $this->smsOperator = User::factory()->create();
        $this->volunteer = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->createMembership($this->owner, 'campaign-owner');
        $this->createMembership($this->strategyDirector, 'strategy-director');
        $this->createMembership($this->commsDirector, 'communications-director');
        $this->createMembership($this->dataAnalyst, 'data-analyst');
        $this->createMembership($this->smsOperator, 'sms-whatsapp-operator');
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
    // RBAC — Strategy Notes
    // =====================================================================

    public function test_strategy_director_can_create_note(): void
    {
        $response = $this->actingAs($this->strategyDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes",
            ['title' => 'SWOT Analysis', 'content' => 'Strengths...', 'category' => 'swot']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('strategy_notes', ['title' => 'SWOT Analysis', 'campaign_id' => $this->campaign->id]);
    }

    public function test_data_analyst_cannot_create_note(): void
    {
        $response = $this->actingAs($this->dataAnalyst)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes",
            ['title' => 'Test Note', 'content' => 'data']
        );

        $response->assertStatus(403);
    }

    public function test_volunteer_cannot_view_strategy_notes(): void
    {
        $response = $this->actingAs($this->volunteer)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes"
        );

        $response->assertStatus(403);
    }

    public function test_strategy_director_can_view_notes(): void
    {
        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Test Note',
            'category' => 'general',
            'clearance_level' => 'internal',
        ]);

        $response = $this->actingAs($this->strategyDirector)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes"
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_strategy_director_can_update_note(): void
    {
        $note = StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Original Title',
            'category' => 'general',
            'clearance_level' => 'internal',
        ]);

        $response = $this->actingAs($this->strategyDirector)->putJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes/{$note->id}",
            ['title' => 'Updated Title']
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('strategy_notes', ['id' => $note->id, 'title' => 'Updated Title']);
    }

    public function test_strategy_director_can_delete_note(): void
    {
        $note = StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Deletable Note',
            'category' => 'general',
            'clearance_level' => 'internal',
        ]);

        $response = $this->actingAs($this->strategyDirector)->deleteJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes/{$note->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('strategy_notes', ['id' => $note->id]);
    }

    // =====================================================================
    // ABAC — Strategy Notes (Clearance + Geographic)
    // =====================================================================

    public function test_clearance_filters_strategy_notes(): void
    {
        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Public Note',
            'category' => 'general',
            'clearance_level' => 'public',
        ]);

        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Top Secret Note',
            'category' => 'risk',
            'clearance_level' => 'top_secret',
        ]);

        // data-analyst with 'internal' clearance should see public + internal, not top_secret
        $response = $this->actingAs($this->dataAnalyst)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes"
        );

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Public Note', $titles);
        $this->assertNotContains('Top Secret Note', $titles);
    }

    public function test_top_secret_clearance_sees_all_notes(): void
    {
        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Confidential Note',
            'category' => 'general',
            'clearance_level' => 'confidential',
        ]);

        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Top Secret Note',
            'category' => 'general',
            'clearance_level' => 'top_secret',
        ]);

        $response = $this->actingAs($this->strategyDirector)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes"
        );

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_geographic_filter_limits_strategy_notes(): void
    {
        // Create ward-scoped data analyst
        $scopedAnalyst = User::factory()->create(['clearance_level' => 'internal']);
        $this->createMembership($scopedAnalyst, 'data-analyst', [
            'assigned_wards' => ['karen'],
        ]);

        // Analyst doesn't have strategy.view — use strategy-director scoped by ward instead
        $scopedDirector = User::factory()->create(['clearance_level' => 'top_secret']);
        $this->createMembership($scopedDirector, 'strategy-director', [
            'assigned_wards' => ['karen'],
        ]);

        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Karen Strategy',
            'ward' => 'karen',
            'category' => 'general',
            'clearance_level' => 'internal',
        ]);

        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Westlands Strategy',
            'ward' => 'westlands',
            'category' => 'general',
            'clearance_level' => 'internal',
        ]);

        StrategyNote::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'National Strategy',
            'ward' => null,
            'category' => 'general',
            'clearance_level' => 'internal',
        ]);

        $response = $this->actingAs($scopedDirector)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes"
        );

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Karen Strategy', $titles);
        $this->assertContains('National Strategy', $titles);
        $this->assertNotContains('Westlands Strategy', $titles);
    }

    // =====================================================================
    // RBAC — Ward Targets
    // =====================================================================

    public function test_strategy_director_can_create_ward_target(): void
    {
        $response = $this->actingAs($this->strategyDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/ward-targets",
            ['ward' => 'karen', 'registered_voters' => 10000, 'target_votes' => 6000, 'priority' => 'high']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('ward_targets', ['ward' => 'karen', 'campaign_id' => $this->campaign->id]);
    }

    public function test_data_analyst_cannot_create_ward_target(): void
    {
        $response = $this->actingAs($this->dataAnalyst)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/ward-targets",
            ['ward' => 'karen', 'registered_voters' => 10000]
        );

        $response->assertStatus(403);
    }

    public function test_duplicate_ward_target_rejected(): void
    {
        WardTarget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'ward' => 'karen',
        ]);

        $response = $this->actingAs($this->strategyDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/ward-targets",
            ['ward' => 'karen', 'registered_voters' => 5000]
        );

        $response->assertStatus(422);
    }

    public function test_data_analyst_can_view_ward_targets(): void
    {
        WardTarget::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'ward' => 'karen',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($this->dataAnalyst)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/ward-targets"
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // =====================================================================
    // RBAC — Polls
    // =====================================================================

    public function test_strategy_director_can_create_poll(): void
    {
        $response = $this->actingAs($this->strategyDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/polls",
            [
                'title' => 'Pre-Election Poll',
                'pollster' => 'IPSOS',
                'poll_date' => '2026-04-15',
                'sample_size' => 1000,
                'results' => [
                    ['candidate' => 'Candidate A', 'percentage' => 52],
                    ['candidate' => 'Candidate B', 'percentage' => 41],
                ],
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('polls', ['title' => 'Pre-Election Poll']);
    }

    public function test_data_analyst_can_view_polls(): void
    {
        Poll::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Weekly Poll',
            'poll_date' => '2026-04-01',
            'results' => [['candidate' => 'A', 'percentage' => 50]],
            'clearance_level' => 'internal',
        ]);

        $response = $this->actingAs($this->dataAnalyst)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/polls"
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_volunteer_cannot_view_polls(): void
    {
        $response = $this->actingAs($this->volunteer)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/polls"
        );

        $response->assertStatus(403);
    }

    public function test_clearance_filters_polls(): void
    {
        Poll::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Public Poll',
            'poll_date' => '2026-04-01',
            'results' => [['candidate' => 'A', 'percentage' => 50]],
            'clearance_level' => 'public',
        ]);

        Poll::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->strategyDirector->id,
            'title' => 'Confidential Poll',
            'poll_date' => '2026-04-02',
            'results' => [['candidate' => 'B', 'percentage' => 45]],
            'clearance_level' => 'confidential',
        ]);

        // data-analyst with 'internal' clearance: sees public + internal, not confidential
        $response = $this->actingAs($this->dataAnalyst)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/polls"
        );

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Public Poll', $titles);
        $this->assertNotContains('Confidential Poll', $titles);
    }

    // =====================================================================
    // RBAC — Message Templates
    // =====================================================================

    public function test_comms_director_can_create_sms_template(): void
    {
        $response = $this->actingAs($this->commsDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/templates",
            ['name' => 'Welcome SMS', 'channel' => 'sms', 'body' => 'Hello {{name}}, welcome!']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('message_templates', ['name' => 'Welcome SMS', 'channel' => 'sms']);
    }

    public function test_sms_operator_can_create_sms_template(): void
    {
        $response = $this->actingAs($this->smsOperator)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/templates",
            ['name' => 'Reminder SMS', 'channel' => 'sms', 'body' => 'Reminder: Vote tomorrow!']
        );

        $response->assertStatus(201);
    }

    public function test_sms_operator_cannot_send_email_campaign(): void
    {
        $msgCampaign = MessageCampaign::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->commsDirector->id,
            'name' => 'Email Blast',
            'channel' => 'email',
            'body' => 'Email body',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->smsOperator)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns/{$msgCampaign->id}/send"
        );

        $response->assertStatus(403);
    }

    public function test_volunteer_cannot_create_template(): void
    {
        $response = $this->actingAs($this->volunteer)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/templates",
            ['name' => 'Test', 'channel' => 'sms', 'body' => 'test']
        );

        $response->assertStatus(403);
    }

    public function test_comms_director_can_approve_template(): void
    {
        $template = MessageTemplate::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->smsOperator->id,
            'name' => 'Awaiting Approval',
            'channel' => 'sms',
            'body' => 'Vote tomorrow!',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->commsDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/templates/{$template->id}/approve"
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('message_templates', ['id' => $template->id, 'status' => 'approved']);
    }

    public function test_sms_operator_cannot_approve_template(): void
    {
        $template = MessageTemplate::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->smsOperator->id,
            'name' => 'Draft Template',
            'channel' => 'sms',
            'body' => 'Test body',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->smsOperator)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/templates/{$template->id}/approve"
        );

        $response->assertStatus(403);
    }

    // =====================================================================
    // RBAC — Message Campaigns
    // =====================================================================

    public function test_comms_director_can_create_message_campaign(): void
    {
        $response = $this->actingAs($this->commsDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns",
            ['name' => 'Get Out The Vote', 'channel' => 'sms', 'body' => 'Go vote!']
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('message_campaigns', ['name' => 'Get Out The Vote', 'status' => 'draft']);
    }

    public function test_comms_director_can_approve_message_campaign(): void
    {
        $msgCampaign = MessageCampaign::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->smsOperator->id,
            'name' => 'Draft Campaign',
            'channel' => 'sms',
            'body' => 'Vote!',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->commsDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns/{$msgCampaign->id}/approve"
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('message_campaigns', ['id' => $msgCampaign->id, 'status' => 'approved']);
    }

    public function test_comms_director_can_send_sms_campaign(): void
    {
        $msgCampaign = MessageCampaign::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->commsDirector->id,
            'name' => 'Approved SMS',
            'channel' => 'sms',
            'body' => 'Go vote!',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->commsDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns/{$msgCampaign->id}/send"
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('message_campaigns', ['id' => $msgCampaign->id, 'status' => 'sending']);
    }

    public function test_cannot_send_unapproved_campaign(): void
    {
        $msgCampaign = MessageCampaign::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->commsDirector->id,
            'name' => 'Still Draft',
            'channel' => 'sms',
            'body' => 'test',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->commsDirector)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns/{$msgCampaign->id}/send"
        );

        $response->assertStatus(422);
    }

    public function test_cannot_edit_sent_campaign(): void
    {
        $msgCampaign = MessageCampaign::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->commsDirector->id,
            'name' => 'Already Sent',
            'channel' => 'sms',
            'body' => 'Sent message',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->commsDirector)->putJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns/{$msgCampaign->id}",
            ['name' => 'Edited']
        );

        $response->assertStatus(422);
    }

    public function test_volunteer_cannot_send_campaign(): void
    {
        $msgCampaign = MessageCampaign::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->commsDirector->id,
            'name' => 'Approved Campaign',
            'channel' => 'sms',
            'body' => 'test',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->volunteer)->postJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns/{$msgCampaign->id}/send"
        );

        $response->assertStatus(403);
    }

    // =====================================================================
    // Non-member denial
    // =====================================================================

    public function test_non_member_cannot_access_strategy(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/notes"
        );

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_messaging(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/templates"
        );

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_polls(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/polls"
        );

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_ward_targets(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/strategy/ward-targets"
        );

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_message_campaigns(): void
    {
        $response = $this->actingAs($this->outsider)->getJson(
            "/api/v1/campaigns/{$this->campaign->id}/messaging/campaigns"
        );

        $response->assertStatus(403);
    }
}
