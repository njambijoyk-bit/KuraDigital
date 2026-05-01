<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\ManifestoPillar;
use App\Models\NewsArticle;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $volunteer;
    private Campaign $campaign;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->site = Site::create([
            'slug' => 'crud-test',
            'candidate_name' => 'CRUD Test Candidate',
            'is_active' => true,
        ]);

        $this->campaign = Campaign::create([
            'name' => 'CRUD Test Campaign',
            'slug' => 'crud-test',
            'site_id' => $this->site->id,
            'level' => 'county',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create();
        $this->volunteer = User::factory()->create();

        $this->createMembership($this->owner, 'campaign-owner');
        $this->createMembership($this->volunteer, 'volunteer');
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

    private function apiUrl(string $path): string
    {
        return "/api/v1/campaigns/{$this->campaign->id}/{$path}";
    }

    // =====================================================================
    // Events CRUD
    // =====================================================================

    public function test_create_event(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson($this->apiUrl('events'), [
                'title' => 'Community Rally',
                'description' => 'A large rally in the town center',
                'date' => '2026-07-01',
                'time' => '10:00 AM',
                'location' => 'Town Hall',
            ]);

        $response->assertCreated()
            ->assertJsonPath('event.title', 'Community Rally')
            ->assertJsonPath('event.location', 'Town Hall');
    }

    public function test_list_events(): void
    {
        Event::create([
            'site_id' => $this->site->id,
            'title' => 'Event A',
            'date' => '2026-07-01',
        ]);
        Event::create([
            'site_id' => $this->site->id,
            'title' => 'Event B',
            'date' => '2026-07-02',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl('events'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_event(): void
    {
        $event = Event::create([
            'site_id' => $this->site->id,
            'title' => 'Show Event',
            'date' => '2026-07-01',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl("events/{$event->id}"));

        $response->assertOk()
            ->assertJsonPath('event.title', 'Show Event');
    }

    public function test_update_event(): void
    {
        $event = Event::create([
            'site_id' => $this->site->id,
            'title' => 'Old Title',
            'date' => '2026-07-01',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson($this->apiUrl("events/{$event->id}"), [
                'title' => 'Updated Title',
                'location' => 'New Venue',
            ]);

        $response->assertOk()
            ->assertJsonPath('event.title', 'Updated Title')
            ->assertJsonPath('event.location', 'New Venue');
    }

    public function test_delete_event(): void
    {
        $event = Event::create([
            'site_id' => $this->site->id,
            'title' => 'To Delete',
            'date' => '2026-07-01',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson($this->apiUrl("events/{$event->id}"));

        $response->assertOk()
            ->assertJsonPath('message', 'Event deleted.');

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_volunteer_cannot_create_event(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson($this->apiUrl('events'), [
                'title' => 'Unauthorized', 'date' => '2026-07-01',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // News CRUD
    // =====================================================================

    public function test_create_news_article(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson($this->apiUrl('news'), [
                'title' => 'Breaking News',
                'excerpt' => 'Short summary',
                'body' => 'Full article content here.',
                'date' => '2026-07-01',
                'status' => 'published',
            ]);

        $response->assertCreated()
            ->assertJsonPath('article.title', 'Breaking News')
            ->assertJsonPath('article.status', 'published')
            ->assertJsonPath('article.is_published', true);
    }

    public function test_list_news_articles(): void
    {
        NewsArticle::create([
            'site_id' => $this->site->id,
            'title' => 'Article A',
            'date' => '2026-07-01',
            'author_id' => $this->owner->id,
            'status' => 'draft',
        ]);
        NewsArticle::create([
            'site_id' => $this->site->id,
            'title' => 'Article B',
            'date' => '2026-07-02',
            'author_id' => $this->owner->id,
            'status' => 'published',
            'is_published' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl('news'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_news_article(): void
    {
        $article = NewsArticle::create([
            'site_id' => $this->site->id,
            'title' => 'Show Article',
            'date' => '2026-07-01',
            'author_id' => $this->owner->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl("news/{$article->id}"));

        $response->assertOk()
            ->assertJsonPath('article.title', 'Show Article');
    }

    public function test_update_news_article(): void
    {
        $article = NewsArticle::create([
            'site_id' => $this->site->id,
            'title' => 'Draft Article',
            'date' => '2026-07-01',
            'author_id' => $this->owner->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson($this->apiUrl("news/{$article->id}"), [
                'title' => 'Published Article',
                'status' => 'published',
            ]);

        $response->assertOk()
            ->assertJsonPath('article.title', 'Published Article')
            ->assertJsonPath('article.is_published', true);
    }

    public function test_delete_news_article(): void
    {
        $article = NewsArticle::create([
            'site_id' => $this->site->id,
            'title' => 'To Delete',
            'date' => '2026-07-01',
            'author_id' => $this->owner->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson($this->apiUrl("news/{$article->id}"));

        $response->assertOk()
            ->assertJsonPath('message', 'News article deleted.');

        $this->assertDatabaseMissing('news_articles', ['id' => $article->id]);
    }

    public function test_volunteer_cannot_create_news(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson($this->apiUrl('news'), [
                'title' => 'Unauthorized', 'date' => '2026-07-01',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // Gallery CRUD
    // =====================================================================

    public function test_create_gallery_item(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson($this->apiUrl('gallery'), [
                'url' => 'https://example.com/photo.jpg',
                'caption' => 'Campaign rally photo',
                'category' => 'rallies',
                'type' => 'image',
            ]);

        $response->assertCreated()
            ->assertJsonPath('item.caption', 'Campaign rally photo')
            ->assertJsonPath('item.type', 'image');
    }

    public function test_list_gallery_items(): void
    {
        GalleryItem::create([
            'site_id' => $this->site->id,
            'url' => 'https://example.com/a.jpg',
            'type' => 'image',
        ]);
        GalleryItem::create([
            'site_id' => $this->site->id,
            'url' => 'https://example.com/b.jpg',
            'type' => 'image',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl('gallery'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_gallery_item(): void
    {
        $item = GalleryItem::create([
            'site_id' => $this->site->id,
            'url' => 'https://example.com/show.jpg',
            'caption' => 'Test',
            'type' => 'image',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl("gallery/{$item->id}"));

        $response->assertOk()
            ->assertJsonPath('item.caption', 'Test');
    }

    public function test_update_gallery_item(): void
    {
        $item = GalleryItem::create([
            'site_id' => $this->site->id,
            'url' => 'https://example.com/old.jpg',
            'caption' => 'Old caption',
            'type' => 'image',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson($this->apiUrl("gallery/{$item->id}"), [
                'caption' => 'Updated caption',
                'category' => 'community',
            ]);

        $response->assertOk()
            ->assertJsonPath('item.caption', 'Updated caption')
            ->assertJsonPath('item.category', 'community');
    }

    public function test_delete_gallery_item(): void
    {
        $item = GalleryItem::create([
            'site_id' => $this->site->id,
            'url' => 'https://example.com/delete.jpg',
            'type' => 'image',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson($this->apiUrl("gallery/{$item->id}"));

        $response->assertOk()
            ->assertJsonPath('message', 'Gallery item deleted.');

        $this->assertDatabaseMissing('gallery_items', ['id' => $item->id]);
    }

    public function test_volunteer_cannot_create_gallery_item(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson($this->apiUrl('gallery'), [
                'url' => 'https://example.com/unauthorized.jpg',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // Manifesto CRUD
    // =====================================================================

    public function test_create_manifesto_pillar(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson($this->apiUrl('manifesto'), [
                'title' => 'Healthcare',
                'description' => 'Improve access to healthcare services',
                'icon' => '🏥',
                'sort_order' => 1,
                'promises' => ['Build 5 clinics', 'Hire 100 nurses'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('pillar.title', 'Healthcare')
            ->assertJsonPath('pillar.icon', '🏥');
    }

    public function test_list_manifesto_pillars(): void
    {
        ManifestoPillar::create([
            'site_id' => $this->site->id,
            'title' => 'Education',
            'icon' => '📚',
        ]);
        ManifestoPillar::create([
            'site_id' => $this->site->id,
            'title' => 'Infrastructure',
            'icon' => '🏗️',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl('manifesto'));

        $response->assertOk()
            ->assertJsonCount(2, 'pillars');
    }

    public function test_show_manifesto_pillar(): void
    {
        $pillar = ManifestoPillar::create([
            'site_id' => $this->site->id,
            'title' => 'Water',
            'icon' => '💧',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl("manifesto/{$pillar->id}"));

        $response->assertOk()
            ->assertJsonPath('pillar.title', 'Water');
    }

    public function test_update_manifesto_pillar(): void
    {
        $pillar = ManifestoPillar::create([
            'site_id' => $this->site->id,
            'title' => 'Old Title',
            'icon' => '📋',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson($this->apiUrl("manifesto/{$pillar->id}"), [
                'title' => 'Updated Title',
                'description' => 'New description',
                'promises' => ['Promise A', 'Promise B'],
            ]);

        $response->assertOk()
            ->assertJsonPath('pillar.title', 'Updated Title')
            ->assertJsonPath('pillar.description', 'New description');
    }

    public function test_delete_manifesto_pillar(): void
    {
        $pillar = ManifestoPillar::create([
            'site_id' => $this->site->id,
            'title' => 'To Delete',
            'icon' => '🗑️',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson($this->apiUrl("manifesto/{$pillar->id}"));

        $response->assertOk()
            ->assertJsonPath('message', 'Manifesto pillar deleted.');

        $this->assertDatabaseMissing('manifesto_pillars', ['id' => $pillar->id]);
    }

    public function test_volunteer_cannot_create_manifesto_pillar(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson($this->apiUrl('manifesto'), [
                'title' => 'Unauthorized',
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // Projects CRUD
    // =====================================================================

    public function test_create_project(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson($this->apiUrl('projects'), [
                'title' => 'Road Construction',
                'description' => 'Build a new road in the constituency',
                'category' => 'infrastructure',
                'status' => 'planned',
            ]);

        $response->assertCreated()
            ->assertJsonPath('project.title', 'Road Construction')
            ->assertJsonPath('project.status', 'planned');
    }

    public function test_list_projects(): void
    {
        Project::create([
            'site_id' => $this->site->id,
            'title' => 'Project A',
            'status' => 'planned',
        ]);
        Project::create([
            'site_id' => $this->site->id,
            'title' => 'Project B',
            'status' => 'ongoing',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl('projects'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_project(): void
    {
        $project = Project::create([
            'site_id' => $this->site->id,
            'title' => 'Show Project',
            'status' => 'planned',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl("projects/{$project->id}"));

        $response->assertOk()
            ->assertJsonPath('project.title', 'Show Project');
    }

    public function test_update_project(): void
    {
        $project = Project::create([
            'site_id' => $this->site->id,
            'title' => 'Draft Project',
            'status' => 'planned',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson($this->apiUrl("projects/{$project->id}"), [
                'title' => 'Active Project',
                'status' => 'ongoing',
                'impact' => 'Benefits 10,000 residents',
            ]);

        $response->assertOk()
            ->assertJsonPath('project.title', 'Active Project')
            ->assertJsonPath('project.status', 'ongoing');
    }

    public function test_delete_project(): void
    {
        $project = Project::create([
            'site_id' => $this->site->id,
            'title' => 'To Delete',
            'status' => 'planned',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson($this->apiUrl("projects/{$project->id}"));

        $response->assertOk()
            ->assertJsonPath('message', 'Project deleted.');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_volunteer_cannot_create_project(): void
    {
        $this->actingAs($this->volunteer)
            ->postJson($this->apiUrl('projects'), [
                'title' => 'Unauthorized',
            ])
            ->assertForbidden();
    }

    public function test_update_project_status_to_completed(): void
    {
        $project = Project::create([
            'site_id' => $this->site->id,
            'title' => 'Finishing Project',
            'status' => 'ongoing',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson($this->apiUrl("projects/{$project->id}"), [
                'status' => 'completed',
            ]);

        $response->assertOk()
            ->assertJsonPath('project.status', 'completed');
    }

    public function test_news_draft_to_published_workflow(): void
    {
        $article = NewsArticle::create([
            'site_id' => $this->site->id,
            'title' => 'Draft Article',
            'date' => '2026-07-01',
            'author_id' => $this->owner->id,
            'status' => 'draft',
            'is_published' => false,
        ]);

        $this->assertFalse($article->is_published);

        $response = $this->actingAs($this->owner)
            ->putJson($this->apiUrl("news/{$article->id}"), [
                'status' => 'published',
            ]);

        $response->assertOk()
            ->assertJsonPath('article.status', 'published')
            ->assertJsonPath('article.is_published', true);
    }

    public function test_event_validation_requires_title_and_date(): void
    {
        $this->actingAs($this->owner)
            ->postJson($this->apiUrl('events'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'date']);
    }

    public function test_gallery_filter_by_category(): void
    {
        GalleryItem::create([
            'site_id' => $this->site->id,
            'url' => 'https://example.com/rally.jpg',
            'category' => 'rallies',
            'type' => 'image',
        ]);
        GalleryItem::create([
            'site_id' => $this->site->id,
            'url' => 'https://example.com/team.jpg',
            'category' => 'team',
            'type' => 'image',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson($this->apiUrl('gallery?category=rallies'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
