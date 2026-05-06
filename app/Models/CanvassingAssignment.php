<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanvassingAssignment extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'campaign_id',
        'assigned_to',
        'assigned_by',
        'title',
        'description',
        'ward',
        'constituency',
        'county',
        'status',
        'priority',
        'due_date',
        'target_voters',
        'started_at',
        'completed_at',
        'completion_notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'target_voters' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(VoterInteraction::class, 'assignment_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(AgentCheckIn::class, 'assignment_id');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
