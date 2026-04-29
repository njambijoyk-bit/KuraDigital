<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'slug', 'candidate_name', 'position', 'constituency', 'county', 'party',
        'slogan', 'slogan_sw', 'primary_color', 'secondary_color',
        'logo_url', 'portrait_url', 'hero_image_url', 'about_image_url',
        'bio_summary', 'bio_summary_sw', 'bio_full', 'education', 'experience',
        'pillars', 'milestones',
        'phone', 'email', 'office_address',
        'facebook_url', 'twitter_url', 'instagram_url', 'tiktok_url', 'youtube_url',
        'donation_info', 'is_active',
    ];

    protected $casts = [
        'pillars' => 'array',
        'milestones' => 'array',
        'is_active' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'id', 'site_id');
    }

    public function getAuditCampaignId(): ?int
    {
        return Campaign::where('site_id', $this->id)->value('id');
    }

    public function manifestoPillars(): HasMany
    {
        return $this->hasMany(ManifestoPillar::class)->orderBy('sort_order');
    }

    public function allManifestoPillars(): HasMany
    {
        return $this->hasMany(ManifestoPillar::class)->orderBy('sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->where('is_published', true)->orderBy('date');
    }

    public function allEvents(): HasMany
    {
        return $this->hasMany(Event::class)->orderByDesc('date');
    }

    public function newsArticles(): HasMany
    {
        return $this->hasMany(NewsArticle::class)->where('is_published', true)->orderByDesc('date');
    }

    public function allNewsArticles(): HasMany
    {
        return $this->hasMany(NewsArticle::class)->orderByDesc('date');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order');
    }

    public function allGalleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('sort_order');
    }

    public function allProjects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('sort_order');
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class);
    }

    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }
}
