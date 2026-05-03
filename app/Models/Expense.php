<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'budget_id',
        'created_by',
        'title',
        'description',
        'amount',
        'currency',
        'category',
        'payment_method',
        'reference',
        'vendor',
        'expense_date',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'ward',
        'constituency',
        'county',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getAuditCampaignId(): int
    {
        return $this->campaign_id;
    }
}
