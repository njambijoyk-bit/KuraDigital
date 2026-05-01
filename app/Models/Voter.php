<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voter extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'phone',
        'national_id',
        'email',
        'supporter_status',
        'source',
        'county',
        'constituency',
        'ward',
        'polling_station',
        'tags',
        'notes',
        'gender',
        'date_of_birth',
        'registered_by',
        'last_contacted_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'date_of_birth' => 'date',
        'last_contacted_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function getAuditCampaignId(): ?int
    {
        return $this->campaign_id;
    }
}
