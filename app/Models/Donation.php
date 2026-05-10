<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasEncryptedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Donation extends Model
{
    use Auditable, HasEncryptedFields;

    protected $fillable = [
        'campaign_id',
        'donor_name',
        'donor_phone',
        'donor_email',
        'donor_id_number',
        'donor_occupation',
        'donor_employer',
        'amount',
        'currency',
        'channel',
        'mpesa_receipt',
        'transaction_id',
        'status',
        'notes',
        'source_type',
        'is_anonymous',
        'requires_disclosure',
        'disclosure_verified_by',
        'disclosure_verified_at',
        'compliance_flags',
        'donated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'requires_disclosure' => 'boolean',
        'donated_at' => 'datetime',
        'disclosure_verified_at' => 'datetime',
        'compliance_flags' => 'array',
        'donor_name' => 'encrypted',
        'donor_phone' => 'encrypted',
        'donor_email' => 'encrypted',
        'donor_id_number' => 'encrypted',
        'donor_occupation' => 'encrypted',
        'donor_employer' => 'encrypted',
    ];

    public function blindIndexFields(): array
    {
        return [
            'donor_phone' => 'donor_phone_index',
            'donor_email' => 'donor_email_index',
            'donor_name' => 'donor_name_index',
            'donor_id_number' => 'donor_id_index',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function mpesaTransaction(): HasOne
    {
        return $this->hasOne(MpesaTransaction::class);
    }

    public function disclosureVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disclosure_verified_by');
    }

    public function getAuditCampaignId(): int
    {
        return $this->campaign_id;
    }
}
