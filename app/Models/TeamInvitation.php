<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'invited_by',
        'email',
        'phone',
        'token',
        'role',
        'assigned_wards',
        'assigned_constituencies',
        'assigned_counties',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'assigned_wards' => 'array',
        'assigned_constituencies' => 'array',
        'assigned_counties' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
