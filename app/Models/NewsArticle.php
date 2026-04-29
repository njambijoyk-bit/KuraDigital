<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticle extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'site_id', 'title', 'excerpt', 'body', 'image_url', 'date',
        'is_published', 'status', 'scheduled_at', 'author_id',
    ];

    protected $casts = [
        'date' => 'date',
        'is_published' => 'boolean',
        'scheduled_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getAuditCampaignId(): ?int
    {
        return Campaign::where('site_id', $this->site_id)->value('id');
    }
}
