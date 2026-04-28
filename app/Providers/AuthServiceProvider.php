<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Media;
use App\Policies\AuditLogPolicy;
use App\Policies\CampaignMemberPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Campaign::class => CampaignPolicy::class,
        CampaignMember::class => CampaignMemberPolicy::class,
        Media::class => MediaPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
