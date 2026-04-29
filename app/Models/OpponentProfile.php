<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpponentProfile extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'campaign_id', 'name', 'party', 'position', 'county', 'constituency', 'ward',
        'photo_url', 'bio', 'strengths_summary', 'weaknesses_summary',
        'threat_level', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function research(): HasMany
    {
        return $this->hasMany(OpponentResearch::class);
    }

    public function swotEntries(): HasMany
    {
        return $this->hasMany(OpponentSwotEntry::class);
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
