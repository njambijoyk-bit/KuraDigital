<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opponent extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'party',
        'position',
        'county',
        'constituency',
        'ward',
        'threat_level',
        'bio',
        'strengths',
        'weaknesses',
        'photo_url',
        'social_facebook',
        'social_twitter',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function research(): HasMany
    {
        return $this->hasMany(OpponentResearch::class);
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
