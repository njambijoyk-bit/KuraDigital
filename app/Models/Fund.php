<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'code',
        'description',
        'is_restricted',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_restricted' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class);
    }
}
