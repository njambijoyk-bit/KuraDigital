<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    protected $fillable = [
        'campaign_id',
        'entry_number',
        'date',
        'description',
        'reference_type',
        'reference_id',
        'posted_by',
        'is_posted',
        'is_reversed',
        'reversed_by_id',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'is_posted' => 'boolean',
        'is_reversed' => 'boolean',
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function getDebitTotalAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getCreditTotalAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->debit_total - $this->credit_total) < 0.01;
    }
}
