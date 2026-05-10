<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignFinanceSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'campaign_id',
        'spending_cap',
        'spending_cap_currency',
        'individual_donation_cap',
        'corporate_donation_cap',
        'disclosure_threshold',
        'approval_limits',
        'election_date',
        'reporting_period',
        'require_receipts_above',
        'require_segregation_of_duties',
        'alert_at_percent',
        'critical_at_percent',
    ];

    protected $casts = [
        'spending_cap' => 'decimal:2',
        'individual_donation_cap' => 'decimal:2',
        'corporate_donation_cap' => 'decimal:2',
        'disclosure_threshold' => 'decimal:2',
        'require_receipts_above' => 'decimal:2',
        'approval_limits' => 'array',
        'election_date' => 'date',
        'require_segregation_of_duties' => 'boolean',
        'alert_at_percent' => 'integer',
        'critical_at_percent' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function getApprovalLimitForRole(string $role): ?float
    {
        $limits = $this->approval_limits ?? [];

        return isset($limits[$role]) ? (float) $limits[$role] : null;
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
