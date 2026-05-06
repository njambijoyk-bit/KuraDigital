<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'created_by',
        'name',
        'category',
        'allocated_amount',
        'spent_amount',
        'period',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getRemainingAttribute(): float
    {
        return (float) $this->allocated_amount - (float) $this->spent_amount;
    }

    public function getUtilizationPercentAttribute(): float
    {
        if ((float) $this->allocated_amount === 0.0) {
            return 0;
        }
        return round(((float) $this->spent_amount / (float) $this->allocated_amount) * 100, 1);
    }

    public function recalculateSpent(): void
    {
        $this->spent_amount = $this->expenses()->where('status', 'approved')->sum('amount');
        $this->save();
    }

    public function getAuditCampaignId(): int
    {
        return $this->campaign_id;
    }
}
