<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\CheckIn;
use App\Models\FieldAgent;
use App\Models\Site;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldOpsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $fieldDirector;
    private User $fieldCoordinator;
    private User $campaignAgent;
    private User $volunteer;
    private User $agentUser;
    private Campaign $campaign;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->site = Site::create([
            'slug' => 'field-test',
            'candidate_name' => 'Field Test',
            'is_active' => true,
        ]);

        $this->campaign = Campaign::create([
            'name' => 'Field Campaign',
            'slug' => 'field-campaign',
            'site_id' => $this->site->id,
            'level' => 'county',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create();
        $this->fieldDirector = User::factory()->create();
        $this->fieldCoordinator = User::factory()->create();
        $this->campaignAgent = User::factory()->create();
        $this->volunteer = User::factory()->create();
        $this->agentUser = User::factory()->create();

        $this->createMembership($this->owner, 'campaign-owner');
        $this->createMembership($this->fieldDirector, 'field-director');
        $this->createMembership($this->fieldCoordinator, 'field-coordinator');
        $this->createMembership($this->campaignAgent, 'campaign-agent');
        $this->createMembership($this->volunteer, 'volunteer');
        $this->createMembership($this->agentUser, 'campaign-agent');
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
    // Field Agents — RBAC
    // =====================================================================

    public function test_field_director_can_manage_agents(): void
    {
        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/field-agents", [
                'user_id' => $this->agentUser->id,
                'agent_code' => 'FA-001',
                'ward' => 'karen',
                'county' => 'nairobi',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('field_agents', [
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'agent_code' => 'FA-001',
        ]);
    }

    public function test_field_coordinator_can_manage_agents(): void
    {
        $response = $this->actingAs($this->fieldCoordinator)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/field-agents", [
                'user_id' => $this->agentUser->id,
                'agent_code' => 'FA-002',
            ]);

        $response->assertStatus(201);
    }

    public function test_volunteer_cannot_manage_agents(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/field-agents", [
                'user_id' => $this->agentUser->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_campaign_agent_cannot_manage_agents(): void
    {
        $response = $this->actingAs($this->campaignAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/field-agents", [
                'user_id' => $this->agentUser->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_field_director_can_view_agents(): void
    {
        FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'agent_code' => 'FA-001',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/field-agents");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_volunteer_cannot_view_agents(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/field-agents");

        $response->assertStatus(403);
    }

    public function test_field_director_can_update_agent(): void
    {
        $agent = FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'agent_code' => 'FA-001',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->putJson("/api/v1/campaigns/{$this->campaign->id}/field-agents/{$agent->id}", [
                'status' => 'suspended',
                'ward' => 'langata',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('field_agents', [
            'id' => $agent->id,
            'status' => 'suspended',
            'ward' => 'langata',
        ]);
    }

    public function test_field_director_can_delete_agent(): void
    {
        $agent = FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'agent_code' => 'FA-001',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->deleteJson("/api/v1/campaigns/{$this->campaign->id}/field-agents/{$agent->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('field_agents', ['id' => $agent->id]);
    }

    public function test_duplicate_agent_rejected(): void
    {
        FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'agent_code' => 'FA-001',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/field-agents", [
                'user_id' => $this->agentUser->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_field_director_can_assign_station(): void
    {
        $agent = FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/field-agents/{$agent->id}/assign-station", [
                'polling_station' => 'Karen Primary School',
                'ward' => 'karen',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('field_agents', [
            'id' => $agent->id,
            'polling_station' => 'Karen Primary School',
        ]);
    }

    // =====================================================================
    // Field Agents — ABAC Geographic Filtering
    // =====================================================================

    public function test_geographic_filter_limits_agents_to_assigned_ward(): void
    {
        FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'ward' => 'karen',
        ]);

        $otherAgent = User::factory()->create();
        $this->createMembership($otherAgent, 'campaign-agent');
        FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $otherAgent->id,
            'ward' => 'westlands',
        ]);

        // Scoped user — only sees karen ward
        $scopedUser = User::factory()->create();
        $this->createMembership($scopedUser, 'field-coordinator', [
            'assigned_wards' => ['karen'],
        ]);

        $response = $this->actingAs($scopedUser)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/field-agents");

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('ward')->all();
        $this->assertContains('karen', $names);
        $this->assertNotContains('westlands', $names);
    }

    public function test_unscoped_user_sees_all_agents(): void
    {
        FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'ward' => 'karen',
        ]);

        $otherAgent = User::factory()->create();
        $this->createMembership($otherAgent, 'campaign-agent');
        FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $otherAgent->id,
            'ward' => 'westlands',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/field-agents");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    // =====================================================================
    // Surveys — RBAC
    // =====================================================================

    public function test_field_director_can_create_survey(): void
    {
        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/surveys", [
                'title' => 'Voter Sentiment Q1',
                'description' => 'Quarterly sentiment survey',
                'questions' => [
                    ['id' => 'q1', 'type' => 'select', 'text' => 'How satisfied are you?', 'options' => ['Very', 'Somewhat', 'Not at all'], 'required' => true],
                    ['id' => 'q2', 'type' => 'text', 'text' => 'Additional comments', 'required' => false],
                ],
                'status' => 'active',
                'ward' => 'karen',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('surveys', [
            'campaign_id' => $this->campaign->id,
            'title' => 'Voter Sentiment Q1',
            'status' => 'active',
        ]);
    }

    public function test_field_coordinator_can_create_survey(): void
    {
        $response = $this->actingAs($this->fieldCoordinator)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/surveys", [
                'title' => 'Door-to-Door Report',
                'questions' => [
                    ['id' => 'q1', 'type' => 'boolean', 'text' => 'Was the voter home?', 'required' => true],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_volunteer_cannot_create_survey(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/surveys", [
                'title' => 'Test Survey',
                'questions' => [
                    ['id' => 'q1', 'type' => 'text', 'text' => 'Question?', 'required' => true],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_campaign_agent_can_submit_survey_response(): void
    {
        $survey = Survey::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'title' => 'Active Survey',
            'questions' => [
                ['id' => 'q1', 'type' => 'text', 'text' => 'Feedback?', 'required' => true],
            ],
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->campaignAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/surveys/{$survey->id}/submit", [
                'answers' => [
                    ['question_id' => 'q1', 'value' => 'Positive feedback from the community'],
                ],
                'ward' => 'karen',
                'latitude' => -1.2921,
                'longitude' => 36.8219,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'submitted_by' => $this->campaignAgent->id,
        ]);
    }

    public function test_volunteer_cannot_submit_survey(): void
    {
        $survey = Survey::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'title' => 'Active Survey',
            'questions' => [
                ['id' => 'q1', 'type' => 'text', 'text' => 'Feedback?', 'required' => true],
            ],
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/surveys/{$survey->id}/submit", [
                'answers' => [
                    ['question_id' => 'q1', 'value' => 'Should not work'],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_submit_to_closed_survey(): void
    {
        $survey = Survey::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'title' => 'Closed Survey',
            'questions' => [
                ['id' => 'q1', 'type' => 'text', 'text' => 'Feedback?', 'required' => true],
            ],
            'status' => 'closed',
        ]);

        $response = $this->actingAs($this->campaignAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/surveys/{$survey->id}/submit", [
                'answers' => [
                    ['question_id' => 'q1', 'value' => 'Late feedback'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_field_director_can_view_survey_responses(): void
    {
        $survey = Survey::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'title' => 'Response Test',
            'questions' => [
                ['id' => 'q1', 'type' => 'text', 'text' => 'Test?', 'required' => true],
            ],
            'status' => 'active',
        ]);

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'submitted_by' => $this->campaignAgent->id,
            'answers' => [['question_id' => 'q1', 'value' => 'Answer']],
            'ward' => 'karen',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/surveys/{$survey->id}/responses");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    // =====================================================================
    // Surveys — ABAC Geographic Filtering
    // =====================================================================

    public function test_geographic_filter_limits_surveys_to_assigned_ward(): void
    {
        Survey::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'title' => 'Karen Survey',
            'questions' => [['id' => 'q1', 'type' => 'text', 'text' => 'Q?']],
            'ward' => 'karen',
        ]);

        Survey::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'title' => 'Westlands Survey',
            'questions' => [['id' => 'q1', 'type' => 'text', 'text' => 'Q?']],
            'ward' => 'westlands',
        ]);

        Survey::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'title' => 'Global Survey',
            'questions' => [['id' => 'q1', 'type' => 'text', 'text' => 'Q?']],
            'ward' => null,
        ]);

        $scopedUser = User::factory()->create();
        $this->createMembership($scopedUser, 'field-coordinator', [
            'assigned_wards' => ['karen'],
        ]);

        $response = $this->actingAs($scopedUser)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/surveys");

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Karen Survey', $titles);
        $this->assertContains('Global Survey', $titles);
        $this->assertNotContains('Westlands Survey', $titles);
    }

    // =====================================================================
    // Check-ins — RBAC
    // =====================================================================

    public function test_campaign_agent_can_check_in(): void
    {
        $response = $this->actingAs($this->campaignAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/check-ins", [
                'latitude' => -1.2921,
                'longitude' => 36.8219,
                'ward' => 'karen',
                'status' => 'on_duty',
                'notes' => 'Starting morning rounds',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('check_ins', [
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->campaignAgent->id,
            'status' => 'on_duty',
        ]);
    }

    public function test_volunteer_cannot_check_in(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/check-ins", [
                'latitude' => -1.2921,
                'longitude' => 36.8219,
            ]);

        $response->assertStatus(403);
    }

    public function test_field_director_can_view_check_ins(): void
    {
        CheckIn::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->campaignAgent->id,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'ward' => 'karen',
            'status' => 'on_duty',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/check-ins");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_volunteer_cannot_view_check_ins(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/check-ins");

        $response->assertStatus(403);
    }

    public function test_check_in_updates_agent_last_active(): void
    {
        $agent = FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->campaignAgent->id,
            'agent_code' => 'FA-001',
        ]);

        $this->assertNull($agent->fresh()->last_active_at);

        $this->actingAs($this->campaignAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/check-ins", [
                'latitude' => -1.2921,
                'longitude' => 36.8219,
                'status' => 'on_duty',
            ]);

        $this->assertNotNull($agent->fresh()->last_active_at);
    }

    // =====================================================================
    // Check-ins — ABAC Geographic Filtering
    // =====================================================================

    public function test_geographic_filter_limits_check_ins_to_assigned_ward(): void
    {
        CheckIn::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->campaignAgent->id,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'ward' => 'karen',
            'status' => 'on_duty',
        ]);

        CheckIn::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->agentUser->id,
            'latitude' => -1.2700,
            'longitude' => 36.8100,
            'ward' => 'westlands',
            'status' => 'on_duty',
        ]);

        $scopedUser = User::factory()->create();
        $this->createMembership($scopedUser, 'field-coordinator', [
            'assigned_wards' => ['karen'],
        ]);

        $response = $this->actingAs($scopedUser)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/check-ins");

        $response->assertStatus(200);
        $wards = collect($response->json('data'))->pluck('ward')->all();
        $this->assertContains('karen', $wards);
        $this->assertNotContains('westlands', $wards);
    }

    // =====================================================================
    // Non-member access — should be denied
    // =====================================================================

    public function test_non_member_cannot_access_field_ops(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/field-agents");

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_surveys(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/surveys");

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_check_ins(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/check-ins");

        $response->assertStatus(403);
    }
}
