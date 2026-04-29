<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestoPillar extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'site_id', 'icon', 'title', 'title_sw', 'description', 'description_sw', 'promises', 'sort_order',
    ];

    protected $casts = [
        'promises' => 'array',
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
