<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpponentSwotEntry extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'opponent_profile_id', 'campaign_id', 'type', 'description',
        'impact_level', 'source', 'created_by',
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
