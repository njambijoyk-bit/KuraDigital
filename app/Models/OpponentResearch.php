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
        'opponent_id',
        'created_by',
        'title',
        'content',
        'clearance',
        'source',
        'date_observed',
        'sentiment_score',
        'sentiment_label',
    ];

    protected $casts = [
        'date_observed' => 'date',
        'sentiment_score' => 'decimal:2',
    ];

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(Opponent::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->opponent?->campaign_id;
    }
}
