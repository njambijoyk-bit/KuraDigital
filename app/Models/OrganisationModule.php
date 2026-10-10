<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationModule extends Model
{
    use Auditable;

    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const REVOKED = 'revoked';

    protected $fillable = [
        'organisation_id',
        'module',
        'plan',
        'status',
        'limits',
        'starts_at',
        'expires_at',
        'granted_by',
        'notes',
    ];

    protected $casts = [
        'limits' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** active | expired | revoked (revoked wins over expired). */
    public function state(): string
    {
        if ($this->status === 'revoked') {
            return self::REVOKED;
        }

        if ($this->starts_at->isFuture() || $this->expires_at->isPast()) {
            return self::EXPIRED;
        }

        return self::ACTIVE;
    }

    public function isActive(): bool
    {
        return $this->state() === self::ACTIVE;
    }

    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function getAuditCampaignId(): ?int
    {
        return null;
    }
}
