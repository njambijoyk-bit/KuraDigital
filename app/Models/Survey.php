<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'created_by',
        'title',
        'description',
        'questions',
        'status',
        'ward',
        'constituency',
        'county',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'questions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
