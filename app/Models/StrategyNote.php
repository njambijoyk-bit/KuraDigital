<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyNote extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'created_by',
        'title',
        'content',
        'category',
        'clearance_level',
        'ward',
        'constituency',
        'county',
    ];

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
