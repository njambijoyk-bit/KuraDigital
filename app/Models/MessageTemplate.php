<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'created_by',
        'name',
        'channel',
        'subject',
        'body',
        'variables',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'approved_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
