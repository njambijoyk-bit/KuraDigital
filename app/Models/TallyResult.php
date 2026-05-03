<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'polling_station_id',
        'submitted_by',
        'candidate_name',
        'party',
        'votes',
        'rejected_votes',
        'total_votes_cast',
        'status',
        'verified_by',
        'verified_at',
        'notes',
        'photo_proof',
    ];

    protected $casts = [
        'votes' => 'integer',
        'rejected_votes' => 'integer',
        'total_votes_cast' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getVotePercentageAttribute(): float
    {
        if ($this->total_votes_cast <= 0) {
            return 0;
        }
        return round(($this->votes / $this->total_votes_cast) * 100, 1);
    }
}
