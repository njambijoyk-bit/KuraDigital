<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\Site;
use App\Models\TallyResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ElectionDayTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;
    private User $fieldDirector;
    private User $stationAgent;
    private User $observer;
    private User $volunteer;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $site = Site::create([
            'slug' => 'eday-test',
            'candidate_name' => 'Election Day Test',
            'is_active' => true,
        ]);
        $this->campaign = Campaign::create([
            'name' => 'E-Day Test Campaign',
            'slug' => 'eday-test-campaign',
            'site_id' => $site->id,
            'level' => 'county',
            'is_active' => true,
        ]);

        $this->fieldDirector = User::factory()->create();
        $this->fieldDirector->assignRole('field-director');
        $this->createMembership($this->fieldDirector, 'field-director');

        $this->stationAgent = User::factory()->create();
        $this->stationAgent->assignRole('polling-station-agent');
        $this->createMembership($this->stationAgent, 'polling-station-agent');

        $this->observer = User::factory()->create();
        $this->observer->assignRole('election-observer');
        $this->createMembership($this->observer, 'election-observer');

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
    // Polling Stations
    // =====================================================================

    public function test_field_director_can_create_station(): void
    {
        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/stations", [
                'name' => 'Kibera Primary School',
                'code' => 'PS-001',
                'ward' => 'Kibera',
                'constituency' => 'Langata',
                'county' => 'Nairobi',
                'registered_voters' => 1200,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'Kibera Primary School', 'code' => 'PS-001']);
        $this->assertDatabaseHas('polling_stations', ['name' => 'Kibera Primary School', 'campaign_id' => $this->campaign->id]);
    }

    public function test_station_agent_cannot_create_station(): void
    {
        $response = $this->actingAs($this->stationAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/stations", [
                'name' => 'Unauthorized Station',
                'code' => 'PS-X',
            ]);

        $response->assertStatus(403);
    }

    public function test_field_director_can_view_stations(): void
    {
        PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Test Station',
            'code' => 'TS-001',
            'ward' => 'TestWard',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/stations");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test Station']);
    }

    public function test_station_agent_can_view_stations(): void
    {
        PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Agent View Station',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->stationAgent)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/stations");

        $response->assertStatus(200);
    }

    public function test_field_director_can_update_station(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Old Name',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->putJson("/api/v1/campaigns/{$this->campaign->id}/election-day/stations/{$station->id}", [
                'name' => 'Updated Name',
                'status' => 'open',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('polling_stations', ['id' => $station->id, 'name' => 'Updated Name', 'status' => 'open']);
    }

    public function test_field_director_can_delete_station(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'To Delete',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->deleteJson("/api/v1/campaigns/{$this->campaign->id}/election-day/stations/{$station->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('polling_stations', ['id' => $station->id]);
    }

    // =====================================================================
    // Tally Results
    // =====================================================================

    public function test_station_agent_can_submit_tally(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Tally Station',
            'registered_voters' => 500,
        ]);

        $response = $this->actingAs($this->stationAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies", [
                'polling_station_id' => $station->id,
                'candidate_name' => 'John Kamau',
                'party' => 'UDA',
                'votes' => 250,
                'rejected_votes' => 5,
                'total_votes_cast' => 480,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['candidate_name' => 'John Kamau', 'votes' => 250]);
        $this->assertDatabaseHas('tally_results', [
            'candidate_name' => 'John Kamau',
            'votes' => 250,
            'status' => 'provisional',
        ]);
    }

    public function test_volunteer_cannot_submit_tally(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'No Submit Station',
        ]);

        $response = $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies", [
                'polling_station_id' => $station->id,
                'candidate_name' => 'Unauthorized',
                'votes' => 100,
            ]);

        $response->assertStatus(403);
    }

    public function test_observer_can_view_tallies(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Observer Station',
        ]);

        TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'Jane Ouma',
            'votes' => 300,
            'total_votes_cast' => 500,
        ]);

        $response = $this->actingAs($this->observer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies");

        $response->assertStatus(200);
        $response->assertJsonFragment(['candidate_name' => 'Jane Ouma']);
    }

    public function test_field_director_can_verify_tally(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Verify Station',
        ]);

        $tally = TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'Verify Candidate',
            'votes' => 200,
            'status' => 'provisional',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies/{$tally->id}/verify");

        $response->assertStatus(200);
        $this->assertDatabaseHas('tally_results', ['id' => $tally->id, 'status' => 'verified', 'verified_by' => $this->fieldDirector->id]);
    }

    public function test_station_agent_cannot_verify_tally(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Agent No Verify',
        ]);

        $tally = TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'No Verify',
            'votes' => 100,
            'status' => 'provisional',
        ]);

        $response = $this->actingAs($this->stationAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies/{$tally->id}/verify");

        $response->assertStatus(403);
    }

    public function test_cannot_edit_verified_tally(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Locked Station',
        ]);

        $tally = TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'Locked Candidate',
            'votes' => 300,
            'status' => 'verified',
            'verified_by' => $this->fieldDirector->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->stationAgent)
            ->putJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies/{$tally->id}", [
                'votes' => 999,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_delete_verified_tally(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'No Delete Station',
        ]);

        $tally = TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'No Delete',
            'votes' => 100,
            'status' => 'verified',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->deleteJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies/{$tally->id}");

        $response->assertStatus(422);
    }

    public function test_field_director_can_dispute_tally(): void
    {
        $station = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Dispute Station',
        ]);

        $tally = TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'Disputed Candidate',
            'votes' => 100,
            'status' => 'provisional',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies/{$tally->id}/dispute", [
                'notes' => 'Numbers do not match the physical form.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tally_results', ['id' => $tally->id, 'status' => 'disputed']);
    }

    // =====================================================================
    // Tally Board
    // =====================================================================

    public function test_tally_board_aggregates_correctly(): void
    {
        $station1 = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Board Station 1',
            'registered_voters' => 1000,
        ]);
        $station2 = PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'Board Station 2',
            'registered_voters' => 800,
        ]);

        TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station1->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'Alice',
            'party' => 'Party A',
            'votes' => 400,
            'total_votes_cast' => 700,
            'status' => 'verified',
        ]);
        TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station1->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'Bob',
            'party' => 'Party B',
            'votes' => 280,
            'total_votes_cast' => 700,
        ]);
        TallyResult::create([
            'campaign_id' => $this->campaign->id,
            'polling_station_id' => $station2->id,
            'submitted_by' => $this->stationAgent->id,
            'candidate_name' => 'Alice',
            'party' => 'Party A',
            'votes' => 350,
            'total_votes_cast' => 600,
        ]);

        $response = $this->actingAs($this->observer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tally-board");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(2, $data['overview']['total_stations']);
        $this->assertEquals(2, $data['overview']['reported_stations']);
        $this->assertEquals(1800, $data['overview']['total_registered']);
        $this->assertEquals(1300, $data['overview']['total_votes_cast']);

        $candidates = collect($data['candidates']);
        $alice = $candidates->firstWhere('candidate_name', 'Alice');
        $this->assertEquals(750, $alice['total_votes']);
        $this->assertEquals(2, $alice['stations_reported']);
    }

    // =====================================================================
    // Incidents
    // =====================================================================

    public function test_station_agent_can_report_incident(): void
    {
        $response = $this->actingAs($this->stationAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents", [
                'title' => 'Ballot box tampering',
                'description' => 'Seal on ballot box C was broken before counting.',
                'category' => 'irregularity',
                'severity' => 'high',
                'ward' => 'Kibera',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['title' => 'Ballot box tampering', 'category' => 'irregularity']);
        $this->assertDatabaseHas('incidents', ['title' => 'Ballot box tampering', 'status' => 'reported']);
    }

    public function test_observer_can_report_incident(): void
    {
        $response = $this->actingAs($this->observer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents", [
                'title' => 'Long queues',
                'description' => 'Voters waited 4+ hours.',
                'category' => 'procedural',
                'severity' => 'medium',
            ]);

        $response->assertStatus(201);
    }

    public function test_volunteer_cannot_report_incident(): void
    {
        $response = $this->actingAs($this->volunteer)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents", [
                'title' => 'Unauthorized report',
                'description' => 'Should not be allowed.',
                'category' => 'other',
            ]);

        $response->assertStatus(403);
    }

    public function test_field_director_can_resolve_incident(): void
    {
        $incident = Incident::create([
            'campaign_id' => $this->campaign->id,
            'reported_by' => $this->stationAgent->id,
            'title' => 'To Resolve',
            'description' => 'Needs resolution.',
            'category' => 'other',
            'severity' => 'low',
            'status' => 'reported',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents/{$incident->id}/resolve", [
                'resolution_notes' => 'Issue was investigated and resolved on site.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'status' => 'resolved', 'resolved_by' => $this->fieldDirector->id]);
    }

    public function test_station_agent_cannot_resolve_incident(): void
    {
        $incident = Incident::create([
            'campaign_id' => $this->campaign->id,
            'reported_by' => $this->stationAgent->id,
            'title' => 'Agent No Resolve',
            'description' => 'Agent cannot resolve.',
            'category' => 'other',
            'status' => 'reported',
        ]);

        $response = $this->actingAs($this->stationAgent)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents/{$incident->id}/resolve", [
                'resolution_notes' => 'Unauthorized resolve.',
            ]);

        $response->assertStatus(403);
    }

    public function test_field_director_can_escalate_incident(): void
    {
        $incident = Incident::create([
            'campaign_id' => $this->campaign->id,
            'reported_by' => $this->stationAgent->id,
            'title' => 'Escalation Test',
            'description' => 'Should be escalated.',
            'category' => 'violence',
            'severity' => 'high',
            'status' => 'reported',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->postJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents/{$incident->id}/escalate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'status' => 'escalated', 'severity' => 'critical']);
    }

    public function test_cannot_edit_resolved_incident(): void
    {
        $incident = Incident::create([
            'campaign_id' => $this->campaign->id,
            'reported_by' => $this->stationAgent->id,
            'title' => 'Resolved Incident',
            'description' => 'Already resolved.',
            'category' => 'other',
            'status' => 'resolved',
            'resolved_by' => $this->fieldDirector->id,
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($this->stationAgent)
            ->putJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents/{$incident->id}", [
                'title' => 'Updated title',
            ]);

        $response->assertStatus(422);
    }

    // =====================================================================
    // Command Centre
    // =====================================================================

    public function test_field_director_can_access_command_centre(): void
    {
        PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'CC Station 1',
            'status' => 'open',
        ]);
        PollingStation::create([
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->fieldDirector->id,
            'name' => 'CC Station 2',
            'status' => 'closed',
        ]);

        Incident::create([
            'campaign_id' => $this->campaign->id,
            'reported_by' => $this->stationAgent->id,
            'title' => 'CC Incident',
            'description' => 'Test incident for command centre.',
            'category' => 'irregularity',
            'severity' => 'high',
            'status' => 'reported',
        ]);

        $response = $this->actingAs($this->fieldDirector)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/command-centre");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(2, $data['stations']['total']);
        $this->assertEquals(1, $data['stations']['open']);
        $this->assertEquals(1, $data['stations']['closed']);
        $this->assertEquals(1, $data['incidents']['total']);
        $this->assertEquals(1, $data['incidents']['unresolved']);
    }

    public function test_observer_cannot_access_command_centre(): void
    {
        $response = $this->actingAs($this->observer)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/command-centre");

        $response->assertStatus(403);
    }

    // =====================================================================
    // Campaign Isolation
    // =====================================================================

    public function test_non_member_cannot_access_stations(): void
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/stations");

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_tallies(): void
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tallies");

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_incidents(): void
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/incidents");

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_access_tally_board(): void
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/campaigns/{$this->campaign->id}/election-day/tally-board");

        $response->assertStatus(403);
    }
}
