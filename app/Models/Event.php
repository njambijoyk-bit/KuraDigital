<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'site_id', 'title', 'title_sw', 'description', 'description_sw', 'date', 'time', 'location', 'map_url', 'is_published',
        'ward', 'constituency', 'county',
    ];

    protected $casts = [
        'date' => 'date',
        'is_published' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function getAuditCampaignId(): ?int
    {
        return Campaign::where('site_id', $this->site_id)->value('id');
    }
}
