<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'campaign_id',
        'code',
        'name',
        'type',
        'parent_id',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class);
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense']);
    }

    public function isCreditNormal(): bool
    {
        return in_array($this->type, ['liability', 'equity', 'revenue']);
    }
}
