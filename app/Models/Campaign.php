<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'candidate_name',
        'position',
        'constituency',
        'county',
        'party',
        'election_year',
        'slogan',
        'primary_color',
        'secondary_color',
        'logo_url',
        'description',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_members')
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps();
    }

    public function campaignMembers(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    public function site(): HasOne
    {
        return $this->hasOne(Site::class);
    }
}
