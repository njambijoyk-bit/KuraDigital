<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'campaign_id',
        'donation_id',
        'transaction_type',
        'merchant_request_id',
        'checkout_request_id',
        'receipt_number',
        'amount',
        'phone_number',
        'result_code',
        'result_desc',
        'raw_callback',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_callback' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
