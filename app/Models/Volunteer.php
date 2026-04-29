<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Volunteer extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'site_id', 'name', 'phone', 'email', 'ward', 'role', 'skills',
        'tags', 'notes', 'engagement_status',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function getAuditCampaignId(): ?int
    {
        return Campaign::where('site_id', $this->site_id)->value('id');
    }
}
