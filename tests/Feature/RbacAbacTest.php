<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\NewsArticle;
use App\Models\Opponent;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAbacTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $contentEditor;
    private User $volunteer;
    private User $researchOfficer;
    private User $fieldDirector;
    private Campaign $campaign;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->site = Site::create([
            'slug' => 'test-campaign',
            'candidate_name' => 'Test Candidate',
            'is_active' => true,
        ]);

        $this->campaign = Campaign::create([
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'site_id' => $this->site->id,
            'level' => 'county',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create();
        $this->contentEditor = User::factory()->create();
        $this->volunteer = User::factory()->create();
        $this->researchOfficer = User::factory()->create();
        $this->fieldDirector = User::factory()->create();

        $this->createMembership($this->owner, 'campaign-owner');
        $this->createMembership($this->contentEditor, 'content-editor');
        $this->createMembership($this->volunteer, 'volunteer');
        $this->createMembership($this->researchOfficer, 'research-officer');
        $this->createMembership($this->fieldDirector, 'field-director');
    }

    private function createMembership(User $user, string $role, array $extra = []): CampaignMember
    {
        return CampaignMember::create(array_merge([
            'user_id' => $user->id,
            'campaign_id' => $this->campaign->id,
            'role' => $role,
            'is_active' => true,
            'visibility_scope' => 'own',
        ], $extra));
    }

    // =====================================================================
    // Events — RBAC
    // =====================================================================

    public function test_campaign_owner_can_crud_events(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/events")
            ->assertOk();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/events", [
                'title' => 'Rally', 'date' => '2026-06-01',
            ])
            ->assertCreated();
    }

    public function test_content_editor_can_create_events(): void
    {
        $this->actingAs($this->contentEditor)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/events", [
                'title' => 'Town Hall', 'date' => '2026-06-15',
            ])
            ->assertCreated();
    }

    public function test_volunteer_cannot_create_events(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/events", [
                'title' => 'Unauthorized Event', 'date' => '2026-06-20',
            ])
            ->assertForbidden();
    }

    public function test_volunteer_can_view_events(): void
    {
        $this->actingAs($this->volunteer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/events")
            ->assertOk();
    }

    public function test_content_editor_cannot_delete_events(): void
    {
        $event = Event::create([
            'site_id' => $this->site->id,
            'title' => 'Test Event',
            'date' => '2026-07-01',
        ]);

        $this->actingAs($this->contentEditor)
            ->deleteJson("/api/v1/campaigns/{$this->campaign->id}/events/{$event->id}")
            ->assertForbidden();
    }

    // =====================================================================
    // News — RBAC
    // =====================================================================

    public function test_content_editor_can_create_news(): void
    {
        $this->actingAs($this->contentEditor)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/news", [
                'title' => 'Breaking News', 'date' => '2026-06-01',
            ])
            ->assertCreated();
    }

    public function test_volunteer_cannot_create_news(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/news", [
                'title' => 'Unauthorized News', 'date' => '2026-06-01',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // Opponents — RBAC
    // =====================================================================

    public function test_research_officer_can_create_opponents(): void
    {
        $this->actingAs($this->researchOfficer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/opponents", [
                'name' => 'Opponent A', 'threat_level' => 'medium',
            ])
            ->assertCreated();
    }

    public function test_volunteer_cannot_create_opponents(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/opponents", [
                'name' => 'Unauthorized Opponent', 'threat_level' => 'low',
            ])
            ->assertForbidden();
    }

    public function test_research_officer_can_add_research(): void
    {
        $opponent = Opponent::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Test Opponent',
            'threat_level' => 'medium',
        ]);

        $this->actingAs($this->researchOfficer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/opponents/{$opponent->id}/research", [
                'title' => 'Intel Report',
                'content' => 'Research findings',
                'clearance' => 'internal',
            ])
            ->assertCreated();
    }

    public function test_volunteer_cannot_view_opponents(): void
    {
        $this->actingAs($this->volunteer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/opponents")
            ->assertForbidden();
    }

    // =====================================================================
    // Gallery — RBAC
    // =====================================================================

    public function test_content_editor_can_create_gallery(): void
    {
        $this->actingAs($this->contentEditor)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/gallery", [
                'url' => 'https://example.com/img.jpg',
                'caption' => 'Rally photo',
            ])
            ->assertCreated();
    }

    public function test_volunteer_cannot_create_gallery(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/gallery", [
                'url' => 'https://example.com/img.jpg',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // Projects — RBAC
    // =====================================================================

    public function test_content_editor_can_edit_projects(): void
    {
        $project = Project::create([
            'site_id' => $this->site->id,
            'title' => 'Road Project',
            'status' => 'planned',
        ]);

        $this->actingAs($this->contentEditor)
            ->putJson("/api/v1/campaigns/{$this->campaign->id}/projects/{$project->id}", [
                'title' => 'Updated Road Project',
            ])
            ->assertOk();
    }

    public function test_volunteer_cannot_edit_projects(): void
    {
        $project = Project::create([
            'site_id' => $this->site->id,
            'title' => 'Bridge Project',
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->volunteer)
            ->putJson("/api/v1/campaigns/{$this->campaign->id}/projects/{$project->id}", [
                'title' => 'Hacked Title',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // Team — RBAC (role hierarchy)
    // =====================================================================

    public function test_campaign_owner_can_invite_members(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/invite", [
                'email' => 'newmember@example.com',
                'role' => 'content-editor',
            ])
            ->assertStatus(201);
    }

    public function test_volunteer_cannot_invite_members(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/invite", [
                'email' => 'unauthorized@example.com',
                'role' => 'content-editor',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // Contacts — RBAC
    // =====================================================================

    public function test_volunteer_cannot_view_contacts(): void
    {
        $this->actingAs($this->volunteer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/contacts")
            ->assertForbidden();
    }

    public function test_campaign_owner_can_view_contacts(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/contacts")
            ->assertOk();
    }

    // =====================================================================
    // Unauthenticated access
    // =====================================================================

    public function test_unauthenticated_cannot_access_api(): void
    {
        $this->getJson("/api/v1/campaigns/{$this->campaign->id}/events")
            ->assertUnauthorized();
    }

    // =====================================================================
    // Non-member access
    // =====================================================================

    public function test_non_member_cannot_access_campaign(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/events")
            ->assertForbidden();
    }

    // =====================================================================
    // ABAC — Geographic filtering (Events)
    // =====================================================================

    public function test_geographic_filter_limits_events_to_assigned_ward(): void
    {
        Event::create([
            'site_id' => $this->site->id,
            'title' => 'Ward A Event',
            'date' => '2026-07-01',
            'ward' => 'ward-a',
        ]);

        Event::create([
            'site_id' => $this->site->id,
            'title' => 'Ward B Event',
            'date' => '2026-07-02',
            'ward' => 'ward-b',
        ]);

        Event::create([
            'site_id' => $this->site->id,
            'title' => 'No Ward Event',
            'date' => '2026-07-03',
        ]);

        $wardUser = User::factory()->create();
        $this->createMembership($wardUser, 'content-editor', [
            'assigned_wards' => ['ward-a'],
        ]);

        $response = $this->actingAs($wardUser)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/events")
            ->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Ward A Event', $titles);
        $this->assertContains('No Ward Event', $titles);
        $this->assertNotContains('Ward B Event', $titles);
    }

    public function test_user_without_ward_assignment_sees_all_events(): void
    {
        Event::create([
            'site_id' => $this->site->id,
            'title' => 'Event 1',
            'date' => '2026-07-01',
            'ward' => 'ward-x',
        ]);

        Event::create([
            'site_id' => $this->site->id,
            'title' => 'Event 2',
            'date' => '2026-07-02',
            'ward' => 'ward-y',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/events")
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // =====================================================================
    // ABAC — Geographic filtering (Projects)
    // =====================================================================

    public function test_geographic_filter_limits_projects_to_assigned_county(): void
    {
        Project::create([
            'site_id' => $this->site->id,
            'title' => 'Nairobi Project',
            'status' => 'planned',
            'county' => 'Nairobi',
        ]);

        Project::create([
            'site_id' => $this->site->id,
            'title' => 'Mombasa Project',
            'status' => 'ongoing',
            'county' => 'Mombasa',
        ]);

        $countyUser = User::factory()->create();
        $this->createMembership($countyUser, 'content-editor', [
            'assigned_counties' => ['Nairobi'],
        ]);

        $response = $this->actingAs($countyUser)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/projects")
            ->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Nairobi Project', $titles);
        $this->assertNotContains('Mombasa Project', $titles);
    }

    // =====================================================================
    // ABAC — Geographic filtering (Contacts)
    // =====================================================================

    public function test_geographic_filter_limits_contacts_to_assigned_constituency(): void
    {
        ContactMessage::create([
            'site_id' => $this->site->id,
            'name' => 'Contact From Langata',
            'message' => 'Hello',
            'constituency' => 'Langata',
        ]);

        ContactMessage::create([
            'site_id' => $this->site->id,
            'name' => 'Contact From Westlands',
            'message' => 'Hi',
            'constituency' => 'Westlands',
        ]);

        $constituencyUser = User::factory()->create();
        $this->createMembership($constituencyUser, 'campaign-owner', [
            'assigned_constituencies' => ['Langata'],
        ]);

        $response = $this->actingAs($constituencyUser)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/contacts")
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Contact From Langata', $names);
        $this->assertNotContains('Contact From Westlands', $names);
    }

    // =====================================================================
    // ABAC — Geographic filtering (Opponents — already implemented)
    // =====================================================================

    public function test_geographic_filter_limits_opponents_to_assigned_ward(): void
    {
        Opponent::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Opponent Ward A',
            'threat_level' => 'high',
            'ward' => 'karen',
        ]);

        Opponent::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Opponent Ward B',
            'threat_level' => 'medium',
            'ward' => 'runda',
        ]);

        $wardOfficer = User::factory()->create();
        $this->createMembership($wardOfficer, 'research-officer', [
            'assigned_wards' => ['karen'],
        ]);

        $response = $this->actingAs($wardOfficer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/opponents")
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Opponent Ward A', $names);
        $this->assertNotContains('Opponent Ward B', $names);
    }

    // =====================================================================
    // ABAC — Clearance-level filtering (Media)
    // =====================================================================

    public function test_media_filtered_by_clearance_level(): void
    {
        $lowClearanceUser = User::factory()->create(['clearance_level' => 'public']);
        $this->createMembership($lowClearanceUser, 'campaign-owner');

        $this->actingAs($lowClearanceUser)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/media")
            ->assertOk();
    }

    // =====================================================================
    // /auth/me returns permissions
    // =====================================================================

    public function test_auth_me_returns_campaign_permissions(): void
    {
        $response = $this->actingAs($this->contentEditor)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $campaigns = $response->json('campaigns');
        $this->assertNotEmpty($campaigns);

        $campaignData = collect($campaigns)->firstWhere('id', $this->campaign->id);
        $this->assertNotNull($campaignData);
        $this->assertEquals('content-editor', $campaignData['role']);
        $this->assertIsArray($campaignData['permissions']);
        $this->assertContains('events.view', $campaignData['permissions']);
        $this->assertContains('events.create', $campaignData['permissions']);
        $this->assertNotContains('events.delete', $campaignData['permissions']);
    }

    public function test_auth_me_returns_role_not_campaign_id(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $campaignData = collect($response->json('campaigns'))->firstWhere('id', $this->campaign->id);
        $this->assertEquals('volunteer', $campaignData['role']);
    }

    // =====================================================================
    // Cross-role permission boundary tests
    // =====================================================================

    public function test_finance_director_cannot_create_events(): void
    {
        $financeDir = User::factory()->create();
        $this->createMembership($financeDir, 'finance-director');

        $this->actingAs($financeDir)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/events", [
                'title' => 'No access', 'date' => '2026-08-01',
            ])
            ->assertForbidden();
    }

    public function test_data_analyst_cannot_create_opponents(): void
    {
        $analyst = User::factory()->create();
        $this->createMembership($analyst, 'data-analyst');

        $this->actingAs($analyst)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/opponents", [
                'name' => 'No access', 'threat_level' => 'low',
            ])
            ->assertForbidden();
    }

    public function test_auditor_cannot_create_events(): void
    {
        $auditor = User::factory()->create();
        $this->createMembership($auditor, 'auditor');

        $this->actingAs($auditor)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/events", [
                'title' => 'Nope', 'date' => '2026-08-01',
            ])
            ->assertForbidden();
    }

    public function test_donor_cannot_view_events(): void
    {
        $donor = User::factory()->create();
        $this->createMembership($donor, 'donor');

        $this->actingAs($donor)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/events")
            ->assertForbidden();
    }
}
