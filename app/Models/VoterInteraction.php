<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoterInteraction extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'campaign_id',
        'voter_id',
        'agent_id',
        'assignment_id',
        'interaction_type',
        'outcome',
        'notes',
        'latitude',
        'longitude',
        'ward',
        'constituency',
        'county',
        'duration_minutes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'duration_minutes' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CanvassingAssignment::class, 'assignment_id');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
