<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
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
        return $this->belongsTo(Campaign::class);
    }

    public function manifestoPillars()
    {
        return $this->hasMany(ManifestoPillar::class)->orderBy('sort_order');
    }

    public function events()
    {
        return $this->hasMany(Event::class)->where('is_published', true)->orderBy('date');
    }

    public function newsArticles()
    {
        return $this->hasMany(NewsArticle::class)->where('is_published', true)->orderByDesc('date');
    }

    public function galleryItems()
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order');
    }

    public function projects()
    {
        return $this->hasMany(Project::class)->orderBy('sort_order');
    }

    public function volunteers()
    {
        return $this->hasMany(Volunteer::class);
    }

    public function contactMessages()
    {
        return $this->hasMany(ContactMessage::class);
    }
}
