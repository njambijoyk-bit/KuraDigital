<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\FieldAgent;
use App\Models\Organisation;
use App\Models\OrganisationModule;
use App\Models\User;
use App\Services\LicenseService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicensingTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $org;
    private Campaign $campaign;
    private User $owner;
    private User $platformOwner;
    private User $platformSupport;
    private LicenseService $license;

    protected function setUp(): void
    {
        parent::setUp();

        // The rest of the suite runs with licensing off; these tests turn it on.
        config(['licensing.enforced' => true]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->license = app(LicenseService::class);

        $this->org = Organisation::create(['name' => 'Acme NGO', 'slug' => 'acme-ngo']);
        $this->campaign = $this->workspace('Acme Main', $this->org);

        $this->owner = $this->member($this->campaign, 'campaign-owner');

        $this->platformOwner = User::factory()->create();
        $this->platformOwner->assignRole('platform-owner');
        $this->platformSupport = User::factory()->create();
        $this->platformSupport->assignRole('platform-support');
    }

    private function workspace(string $name, ?Organisation $org, ?Campaign $parent = null): Campaign
    {
        return Campaign::create([
            'name' => $name,
            'slug' => str($name)->slug() . '-' . uniqid(),
            'level' => 'county',
            'organisation_id' => $org?->id,
            'parent_id' => $parent?->id,
            'is_active' => true,
        ]);
    }

    private function member(Campaign $campaign, string $role): User
    {
        $user = User::factory()->create();
        CampaignMember::create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'role' => $role,
            'is_active' => true,
            'visibility_scope' => 'own_campaign',
        ]);

        return $user;
    }

    private function url(string $path, ?Campaign $campaign = null): string
    {
        return '/api/v1/campaigns/' . ($campaign ?? $this->campaign)->id . $path;
    }

    // ------------------------------------------------------------------
    // Module gating
    // ------------------------------------------------------------------

    public function test_unlicensed_module_is_blocked_with_a_code(): void
    {
        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertStatus(403)
            ->assertJson(['code' => 'module_not_licensed', 'module' => 'finance']);
    }

    public function test_granted_module_is_allowed(): void
    {
        $this->license->grant($this->org, 'finance');

        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertOk();
    }

    public function test_a_grant_only_unlocks_its_own_module(): void
    {
        $this->license->grant($this->org, 'finance');

        $this->actingAs($this->owner)
            ->getJson($this->url('/election-day/stations'))
            ->assertStatus(403)
            ->assertJson(['code' => 'module_not_licensed', 'module' => 'election_day']);
    }

    public function test_core_routes_work_without_any_module(): void
    {
        $this->actingAs($this->owner)->getJson($this->url('/members'))->assertOk();
        $this->actingAs($this->owner)->getJson($this->url('/license'))->assertOk();
        $this->actingAs($this->owner)->getJson($this->url(''))->assertOk();
    }

    public function test_expired_module_is_blocked_then_renewing_restores_it(): void
    {
        $grant = $this->license->grant($this->org, 'finance');
        $grant->forceFill(['expires_at' => now()->subDay()])->save();

        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertStatus(403)
            ->assertJson(['code' => 'module_expired']);

        $this->license->renew($grant->fresh());

        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertOk();
    }

    public function test_revoked_module_is_blocked(): void
    {
        $grant = $this->license->grant($this->org, 'finance');
        $this->license->revoke($grant);

        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertStatus(403)
            ->assertJson(['code' => 'module_revoked']);
    }

    public function test_suspended_organisation_loses_every_module(): void
    {
        $this->license->grant($this->org, 'finance');
        $this->org->update(['status' => 'suspended']);

        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertStatus(403)
            ->assertJson(['code' => 'organisation_suspended']);
    }

    public function test_workspace_without_an_organisation_is_blocked(): void
    {
        $orphan = $this->workspace('Orphan', null);
        $user = $this->member($orphan, 'campaign-owner');

        $this->actingAs($user)
            ->getJson($this->url('/finance/budgets', $orphan))
            ->assertStatus(403)
            ->assertJson(['code' => 'no_organisation']);
    }

    public function test_platform_staff_bypass_module_checks(): void
    {
        // Feature policies still need workspace membership (existing behaviour),
        // so assert only that licensing itself never blocks platform staff.
        foreach ([$this->platformOwner, $this->platformSupport] as $staff) {
            foreach (['/finance/budgets', '/election-day/stations'] as $path) {
                $code = $this->actingAs($staff)->getJson($this->url($path))->json('code');
                $this->assertNotContains($code, ['module_not_licensed', 'no_organisation', 'organisation_suspended']);
            }
        }

        // The same request from a member of an unlicensed workspace is blocked.
        $this->actingAs($this->owner)->getJson($this->url('/finance/budgets'))
            ->assertJson(['code' => 'module_not_licensed']);
    }

    public function test_enforcement_can_be_switched_off(): void
    {
        config(['licensing.enforced' => false]);

        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertOk();
    }

    public function test_every_gated_route_names_a_real_module(): void
    {
        $known = array_keys(config('licensing.modules'));

        $gated = 0;
        foreach (app('router')->getRoutes() as $route) {
            foreach (app('router')->gatherRouteMiddleware($route) as $middleware) {
                if (is_string($middleware) && str_contains($middleware, 'EnsureModuleLicensed:')) {
                    $module = substr($middleware, strrpos($middleware, ':') + 1);
                    $this->assertContains($module, $known, "Route {$route->uri()} uses unknown module '{$module}'");
                    $gated++;
                }
            }
        }

        $this->assertGreaterThan(200, $gated, 'Expected the feature routes to be module-gated.');
    }

    // ------------------------------------------------------------------
    // One licence, several workspaces
    // ------------------------------------------------------------------

    public function test_licence_covers_every_workspace_of_the_organisation(): void
    {
        $this->license->grant($this->org, 'finance');
        $second = $this->workspace('Acme Second', $this->org);
        $user = $this->member($second, 'campaign-owner');

        $this->actingAs($user)
            ->getJson($this->url('/finance/budgets', $second))
            ->assertOk();
    }

    public function test_other_organisations_are_not_affected(): void
    {
        $this->license->grant($this->org, 'finance');

        $other = Organisation::create(['name' => 'Other', 'slug' => 'other']);
        $otherCampaign = $this->workspace('Other WS', $other);
        $user = $this->member($otherCampaign, 'campaign-owner');

        $this->actingAs($user)
            ->getJson($this->url('/finance/budgets', $otherCampaign))
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Terms, renewal, plans
    // ------------------------------------------------------------------

    public function test_a_grant_lasts_twelve_months_by_default(): void
    {
        $grant = $this->license->grant($this->org, 'finance');

        $this->assertTrue($grant->expires_at->isSameDay(now()->addMonths(12)));
    }

    public function test_renewing_early_extends_from_the_current_end_date(): void
    {
        $grant = $this->license->grant($this->org, 'finance');
        $end = $grant->expires_at->copy();

        $renewed = $this->license->renew($grant);

        $this->assertTrue($renewed->expires_at->isSameDay($end->addMonths(12)));
    }

    public function test_renewing_a_lapsed_grant_extends_from_today(): void
    {
        $grant = $this->license->grant($this->org, 'finance');
        $grant->forceFill(['expires_at' => now()->subMonths(5)])->save();

        $renewed = $this->license->renew($grant->fresh());

        $this->assertTrue($renewed->expires_at->isSameDay(now()->addMonths(12)));
    }

    public function test_regranting_resets_a_revoked_module(): void
    {
        $grant = $this->license->grant($this->org, 'finance');
        $this->license->revoke($grant);

        $this->license->grant($this->org, 'finance');

        $this->assertSame(1, $this->org->modules()->where('module', 'finance')->count());
        $this->assertSame(OrganisationModule::ACTIVE, $this->org->modules()->first()->state());
    }

    public function test_granting_a_plan_grants_its_modules_and_limits(): void
    {
        $this->license->grantPlan($this->org, 'fieldos');

        $keys = $this->org->modules()->pluck('module')->all();
        $this->assertEqualsCanonicalizing(config('licensing.plans.fieldos.modules'), $keys);
        $this->assertSame(
            config('licensing.plans.fieldos.limits.field_ops.agents'),
            $this->license->grantFor($this->campaign, 'field_ops')->limit('agents'),
        );
    }

    public function test_unknown_module_or_plan_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->license->grant($this->org, 'teleportation');
    }

    // ------------------------------------------------------------------
    // Limits
    // ------------------------------------------------------------------

    public function test_agent_limit_is_shared_across_workspaces(): void
    {
        $this->license->grant($this->org, 'field_ops', limits: ['agents' => 2]);
        $second = $this->workspace('Acme Second', $this->org);
        $director = $this->member($this->campaign, 'field-director');

        FieldAgent::create(['campaign_id' => $this->campaign->id, 'user_id' => User::factory()->create()->id, 'agent_code' => 'A1']);
        FieldAgent::create(['campaign_id' => $second->id, 'user_id' => User::factory()->create()->id, 'agent_code' => 'A2']);

        $this->actingAs($director)
            ->postJson($this->url('/field-agents'), [
                'user_id' => User::factory()->create()->id,
                'agent_code' => 'A3',
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'agent_limit', 'limit' => 2]);
    }

    public function test_inactive_agents_do_not_use_a_seat(): void
    {
        $this->license->grant($this->org, 'field_ops', limits: ['agents' => 1]);
        $director = $this->member($this->campaign, 'field-director');

        FieldAgent::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => User::factory()->create()->id,
            'agent_code' => 'A1',
            'status' => 'inactive',
        ]);

        $this->actingAs($director)
            ->postJson($this->url('/field-agents'), [
                'user_id' => User::factory()->create()->id,
                'agent_code' => 'A2',
            ])
            ->assertCreated();
    }

    public function test_no_agent_limit_means_unlimited(): void
    {
        $this->license->grant($this->org, 'field_ops');
        $director = $this->member($this->campaign, 'field-director');

        foreach (range(1, 3) as $i) {
            $this->actingAs($director)
                ->postJson($this->url('/field-agents'), [
                    'user_id' => User::factory()->create()->id,
                    'agent_code' => "A{$i}",
                ])
                ->assertCreated();
        }
    }

    // ------------------------------------------------------------------
    // Creating workspaces
    // ------------------------------------------------------------------

    private function newWorkspace(array $extra = []): array
    {
        return array_merge([
            'name' => 'New Workspace',
            'level' => 'county',
            'election_type' => 'other',
        ], $extra);
    }

    public function test_a_new_user_gets_their_own_organisation_with_no_modules(): void
    {
        $newcomer = User::factory()->create();

        $response = $this->actingAs($newcomer)
            ->postJson('/api/v1/campaigns', $this->newWorkspace())
            ->assertCreated();

        $campaign = Campaign::find($response->json('campaign.id'));
        $this->assertNotNull($campaign->organisation_id);
        $this->assertNotEquals($this->org->id, $campaign->organisation_id);
        $this->assertSame(0, $campaign->organisation->modules()->count());

        $this->actingAs($newcomer)
            ->getJson($this->url('/finance/budgets', $campaign))
            ->assertStatus(403);
    }

    public function test_child_workspace_inherits_the_parents_organisation(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/campaigns', $this->newWorkspace(['parent_id' => $this->campaign->id]))
            ->assertCreated();

        $this->assertSame($this->org->id, Campaign::find($response->json('campaign.id'))->organisation_id);
    }

    public function test_leader_can_add_a_workspace_to_their_organisation(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/campaigns', $this->newWorkspace(['organisation_id' => $this->org->id]))
            ->assertCreated();

        $this->assertSame($this->org->id, Campaign::find($response->json('campaign.id'))->organisation_id);
    }

    public function test_user_cannot_add_workspaces_to_someone_elses_organisation(): void
    {
        $other = Organisation::create(['name' => 'Other', 'slug' => 'other']);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/campaigns', $this->newWorkspace(['organisation_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_workspace_limit_is_enforced(): void
    {
        $this->org->update(['max_workspaces' => 1]);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/campaigns', $this->newWorkspace(['organisation_id' => $this->org->id]))
            ->assertStatus(403)
            ->assertJson(['code' => 'workspace_limit']);
    }

    // ------------------------------------------------------------------
    // The workspace licence view
    // ------------------------------------------------------------------

    public function test_members_can_see_what_the_workspace_is_licensed_for(): void
    {
        $this->license->grant($this->org, 'finance');

        $response = $this->actingAs($this->owner)
            ->getJson($this->url('/license'))
            ->assertOk()
            ->assertJsonPath('organisation.name', 'Acme NGO');

        $states = collect($response->json('modules'))->pluck('state', 'key');
        $this->assertSame('active', $states['finance']);
        $this->assertSame('not_licensed', $states['election_day']);
    }

    // ------------------------------------------------------------------
    // Platform admin API
    // ------------------------------------------------------------------

    public function test_regular_users_cannot_use_the_platform_api(): void
    {
        $this->actingAs($this->owner)->getJson('/api/v1/platform/organisations')->assertForbidden();
        $this->actingAs($this->owner)->postJson('/api/v1/platform/organisations', ['name' => 'X'])->assertForbidden();
    }

    public function test_support_can_read_but_not_write(): void
    {
        $this->actingAs($this->platformSupport)->getJson('/api/v1/platform/organisations')->assertOk();
        $this->actingAs($this->platformSupport)->getJson('/api/v1/platform/catalogue')->assertOk();
        $this->actingAs($this->platformSupport)
            ->postJson("/api/v1/platform/organisations/{$this->org->id}/modules", ['module' => 'finance'])
            ->assertForbidden();
    }

    public function test_platform_owner_creates_an_organisation_with_a_plan(): void
    {
        $response = $this->actingAs($this->platformOwner)
            ->postJson('/api/v1/platform/organisations', [
                'name' => 'New Client',
                'contact_email' => 'ops@client.test',
                'plan' => 'fieldos',
            ])
            ->assertCreated();

        $org = Organisation::find($response->json('organisation.id'));
        $this->assertSame('New Client', $org->name);
        $this->assertEqualsCanonicalizing(
            config('licensing.plans.fieldos.modules'),
            $org->modules()->pluck('module')->all(),
        );
    }

    public function test_platform_owner_can_grant_renew_and_revoke_a_module(): void
    {
        $base = "/api/v1/platform/organisations/{$this->org->id}";

        $this->actingAs($this->platformOwner)
            ->postJson("{$base}/modules", ['module' => 'finance', 'months' => 6])
            ->assertCreated();

        $grant = $this->org->modules()->where('module', 'finance')->first();
        $this->assertTrue($grant->expires_at->isSameDay(now()->addMonths(6)));

        $this->actingAs($this->platformOwner)
            ->postJson("{$base}/modules/finance/renew")
            ->assertOk();
        $this->assertTrue($grant->fresh()->expires_at->isSameDay(now()->addMonths(18)));

        $this->actingAs($this->platformOwner)
            ->deleteJson("{$base}/modules/finance")
            ->assertOk();
        $this->assertSame('revoked', $grant->fresh()->state());
    }

    public function test_grant_rejects_unknown_modules_and_plans(): void
    {
        $base = "/api/v1/platform/organisations/{$this->org->id}/modules";

        $this->actingAs($this->platformOwner)->postJson($base, ['module' => 'nope'])->assertStatus(422);
        $this->actingAs($this->platformOwner)->postJson($base, ['plan' => 'nope'])->assertStatus(422);
        $this->actingAs($this->platformOwner)->postJson($base, [])->assertStatus(422);
    }

    public function test_renewing_a_module_the_organisation_never_had_is_a_404(): void
    {
        $this->actingAs($this->platformOwner)
            ->postJson("/api/v1/platform/organisations/{$this->org->id}/modules/finance/renew")
            ->assertNotFound();
    }

    public function test_attaching_a_workspace_moves_its_children_too(): void
    {
        $loose = $this->workspace('Loose Parent', null);
        $child = $this->workspace('Loose Child', null, $loose);

        $this->actingAs($this->platformOwner)
            ->postJson("/api/v1/platform/organisations/{$this->org->id}/campaigns", ['campaign_id' => $loose->id])
            ->assertOk();

        $this->assertSame($this->org->id, $loose->fresh()->organisation_id);
        $this->assertSame($this->org->id, $child->fresh()->organisation_id);
    }

    public function test_suspending_an_organisation_through_the_api_locks_it_out(): void
    {
        $this->license->grant($this->org, 'finance');

        $this->actingAs($this->platformOwner)
            ->putJson("/api/v1/platform/organisations/{$this->org->id}", ['status' => 'suspended'])
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson($this->url('/finance/budgets'))
            ->assertStatus(403)
            ->assertJson(['code' => 'organisation_suspended']);
    }

    public function test_grants_are_audited(): void
    {
        $this->actingAs($this->platformOwner)
            ->postJson("/api/v1/platform/organisations/{$this->org->id}/modules", ['module' => 'finance'])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => OrganisationModule::class,
            'action' => 'created',
        ]);
    }
}
