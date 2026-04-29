<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpponentResearch extends Model
{
    use Auditable, HasFactory;

    protected $table = 'opponent_research';

    protected $fillable = [
        'opponent_profile_id', 'campaign_id', 'title', 'content', 'source_url', 'source_name',
        'category', 'clearance_level', 'date_published', 'attached_media_url', 'created_by',
    ];

    protected $casts = [
        'date_published' => 'date',
    ];

    public function opponentProfile(): BelongsTo
    {
        return $this->belongsTo(OpponentProfile::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
