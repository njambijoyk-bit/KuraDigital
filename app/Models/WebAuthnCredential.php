<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnCredential extends Model
{
    protected $table = 'webauthn_credentials';

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'attestation_type',
        'aaguid',
        'sign_count',
        'device_name',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'sign_count' => 'integer',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function incrementSignCount(): void
    {
        $this->increment('sign_count');
        $this->update(['last_used_at' => now()]);
    }
}
