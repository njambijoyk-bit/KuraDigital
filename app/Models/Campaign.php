<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'site_id',
        'name',
        'slug',
        'level',
        'election_type',
        'county',
        'constituency',
        'ward',
        'party',
        'coalition',
        'election_phase',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Campaign::class, 'parent_id');
    }

    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(CampaignMember::class)->where('is_active', true);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'campaign_members')
            ->withPivot([
                'visibility_scope', 'assigned_wards', 'assigned_constituencies',
                'assigned_counties', 'assigned_polling_stations', 'is_active',
            ])
            ->withTimestamps();
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function opponents(): HasMany
    {
        return $this->hasMany(Opponent::class);
    }

    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class);
    }

    public function fieldAgents(): HasMany
    {
        return $this->hasMany(FieldAgent::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function strategyNotes(): HasMany
    {
        return $this->hasMany(StrategyNote::class);
    }

    public function wardTargets(): HasMany
    {
        return $this->hasMany(WardTarget::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function messageCampaigns(): HasMany
    {
        return $this->hasMany(MessageCampaign::class);
    }

    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }
        return array_reverse($ancestors);
    }

    public function getAuditCampaignId(): int
    {
        return $this->id;
    }

    public function isDescendantOf(Campaign $campaign): bool
    {
        $current = $this->parent;
        while ($current) {
            if ($current->id === $campaign->id) {
                return true;
            }
            $current = $current->parent;
        }
        return false;
    }
}
