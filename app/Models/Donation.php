<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Donation extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'donor_name',
        'donor_phone',
        'donor_email',
        'amount',
        'currency',
        'channel',
        'mpesa_receipt',
        'transaction_id',
        'status',
        'notes',
        'is_anonymous',
        'donated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'donated_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function mpesaTransaction(): HasOne
    {
        return $this->hasOne(MpesaTransaction::class);
    }

    public function getAuditCampaignId(): int
    {
        return $this->campaign_id;
    }
}
