<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSchedule extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'field_agent_id',
        'title',
        'shift_type',
        'date',
        'start_time',
        'end_time',
        'ward',
        'constituency',
        'county',
        'polling_station',
        'status',
        'notes',
        'assigned_by',
        'checked_in_at',
    ];

    protected $casts = [
        'date' => 'date',
        'checked_in_at' => 'datetime',
    ];

    public const SHIFT_PRESETS = [
        'morning'   => ['start' => '06:00', 'end' => '12:00'],
        'afternoon' => ['start' => '12:00', 'end' => '18:00'],
        'evening'   => ['start' => '18:00', 'end' => '00:00'],
        'night'     => ['start' => '00:00', 'end' => '06:00'],
        'full_day'  => ['start' => '06:00', 'end' => '18:00'],
    ];

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'];

    public const SHIFT_TYPES = ['morning', 'afternoon', 'evening', 'night', 'full_day', 'custom'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function fieldAgent(): BelongsTo
    {
        return $this->belongsTo(FieldAgent::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
