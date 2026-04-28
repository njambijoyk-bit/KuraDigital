<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id', 'title', 'excerpt', 'body', 'image_url', 'date', 'is_published',
    ];

    protected $casts = [
        'date' => 'date',
        'is_published' => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
