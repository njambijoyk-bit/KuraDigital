<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldAgent extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'agent_code',
        'status',
        'ward',
        'constituency',
        'county',
        'polling_station',
        'phone',
        'notes',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'user_id', 'user_id')
            ->where('campaign_id', $this->campaign_id);
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
