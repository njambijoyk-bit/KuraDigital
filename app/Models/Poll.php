<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Poll extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'created_by',
        'title',
        'pollster',
        'poll_date',
        'sample_size',
        'margin_of_error',
        'results',
        'ward',
        'constituency',
        'county',
        'clearance_level',
        'notes',
    ];

    protected $casts = [
        'results' => 'array',
        'poll_date' => 'date',
        'margin_of_error' => 'decimal:2',
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
