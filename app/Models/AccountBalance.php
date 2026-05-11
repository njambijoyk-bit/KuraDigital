<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalance extends Model
{
    protected $fillable = [
        'account_id',
        'campaign_id',
        'fund_id',
        'period',
        'debit_total',
        'credit_total',
        'balance',
    ];

    protected $casts = [
        'debit_total' => 'decimal:2',
        'credit_total' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }
}
