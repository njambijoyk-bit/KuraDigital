<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageCampaign extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'created_by',
        'template_id',
        'name',
        'channel',
        'subject',
        'body',
        'status',
        'audience_filters',
        'total_recipients',
        'sent_count',
        'failed_count',
        'scheduled_at',
        'sent_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'audience_filters' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
