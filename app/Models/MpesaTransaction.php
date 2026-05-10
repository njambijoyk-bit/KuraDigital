<?php

namespace App\Models;

use App\Traits\HasEncryptedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaTransaction extends Model
{
    use HasEncryptedFields;
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
        'phone_number' => 'encrypted',
        'receipt_number' => 'encrypted',
    ];

    public function blindIndexFields(): array
    {
        return [
            'phone_number' => 'phone_index',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
