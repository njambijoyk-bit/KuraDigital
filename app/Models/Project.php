<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'site_id', 'title', 'description', 'category', 'status', 'image_url', 'impact', 'sort_order',
        'ward', 'constituency', 'county',
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
