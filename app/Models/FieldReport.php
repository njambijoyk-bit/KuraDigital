<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldReport extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'field_agent_id',
        'type',
        'title',
        'body',
        'latitude',
        'longitude',
        'ward',
        'constituency',
        'county',
        'status',
        'captured_at',
        'client_id',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'captured_at' => 'datetime',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fieldAgent(): BelongsTo
    {
        return $this->belongsTo(FieldAgent::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(FieldReportMedia::class)->orderBy('sort_order');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
