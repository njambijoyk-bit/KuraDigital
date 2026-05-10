<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasEncryptedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Auditable, HasEncryptedFields;

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
        'receipt_media_id',
        'compliance_flags',
        'abac_override_by',
        'abac_override_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'compliance_flags' => 'array',
        'vendor' => 'encrypted',
        'reference' => 'encrypted',
    ];

    public function blindIndexFields(): array
    {
        return [
            'vendor' => 'vendor_index',
        ];
    }

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

    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abac_override_by');
    }

    public function getAuditCampaignId(): int
    {
        return $this->campaign_id;
    }
}
