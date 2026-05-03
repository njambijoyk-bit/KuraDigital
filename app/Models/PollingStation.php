<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollingStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'created_by',
        'name',
        'code',
        'ward',
        'constituency',
        'county',
        'registered_voters',
        'latitude',
        'longitude',
        'status',
        'assigned_agent_id',
        'notes',
    ];

    protected $casts = [
        'registered_voters' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function tallyResults(): HasMany
    {
        return $this->hasMany(TallyResult::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function getTurnoutPercentAttribute(): float
    {
        if ($this->registered_voters <= 0) {
            return 0;
        }
        $totalCast = $this->tallyResults()->max('total_votes_cast') ?? 0;
        return round(($totalCast / $this->registered_voters) * 100, 1);
    }
}
