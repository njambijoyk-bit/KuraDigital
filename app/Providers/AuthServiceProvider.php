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
use App\Models\StrategyNote;
use App\Models\WardTarget;
use App\Models\Poll;
use App\Models\MessageTemplate;
use App\Models\MessageCampaign;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Donation;
use App\Models\PollingStation;
use App\Models\TallyResult;
use App\Models\Incident;
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
use App\Policies\StrategyNotePolicy;
use App\Policies\WardTargetPolicy;
use App\Policies\PollPolicy;
use App\Policies\MessageTemplatePolicy;
use App\Policies\MessageCampaignPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\DonationPolicy;
use App\Policies\PollingStationPolicy;
use App\Policies\TallyResultPolicy;
use App\Policies\IncidentPolicy;
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
        StrategyNote::class => StrategyNotePolicy::class,
        WardTarget::class => WardTargetPolicy::class,
        Poll::class => PollPolicy::class,
        MessageTemplate::class => MessageTemplatePolicy::class,
        MessageCampaign::class => MessageCampaignPolicy::class,
        Budget::class => BudgetPolicy::class,
        Expense::class => ExpensePolicy::class,
        Donation::class => DonationPolicy::class,
        PollingStation::class => PollingStationPolicy::class,
        TallyResult::class => TallyResultPolicy::class,
        Incident::class => IncidentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
