<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\ManifestoPillar;
use App\Models\Media;
use App\Models\NewsArticle;
use App\Models\Project;
use App\Models\Site;
use App\Models\Voter;
use App\Models\Volunteer;
use App\Models\FieldAgent;
use App\Models\Survey;
use App\Models\CheckIn;
use App\Models\FieldReport;
use App\Policies\AuditLogPolicy;
use App\Policies\CampaignMemberPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryItemPolicy;
use App\Policies\ManifestoPillarPolicy;
use App\Policies\MediaPolicy;
use App\Policies\NewsArticlePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SitePolicy;
use App\Policies\VoterPolicy;
use App\Policies\VolunteerPolicy;
use App\Policies\FieldAgentPolicy;
use App\Policies\SurveyPolicy;
use App\Policies\CheckInPolicy;
use App\Policies\FieldReportPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Campaign::class => CampaignPolicy::class,
        CampaignMember::class => CampaignMemberPolicy::class,
        Media::class => MediaPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        Site::class => SitePolicy::class,
        ManifestoPillar::class => ManifestoPillarPolicy::class,
        Event::class => EventPolicy::class,
        NewsArticle::class => NewsArticlePolicy::class,
        GalleryItem::class => GalleryItemPolicy::class,
        Project::class => ProjectPolicy::class,
        Volunteer::class => VolunteerPolicy::class,
        Voter::class => VoterPolicy::class,
        ContactMessage::class => ContactMessagePolicy::class,
        FieldAgent::class => FieldAgentPolicy::class,
        Survey::class => SurveyPolicy::class,
        CheckIn::class => CheckInPolicy::class,
        FieldReport::class => FieldReportPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
