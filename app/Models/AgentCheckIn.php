<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCheckIn extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'assignment_id',
        'latitude',
        'longitude',
        'location_name',
        'ward',
        'constituency',
        'county',
        'check_in_type',
        'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
