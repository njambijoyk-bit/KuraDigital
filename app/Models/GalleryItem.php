<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id', 'url', 'caption', 'category', 'type', 'sort_order',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
