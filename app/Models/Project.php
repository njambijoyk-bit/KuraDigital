<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id', 'title', 'description', 'category', 'status', 'image_url', 'impact', 'sort_order',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
