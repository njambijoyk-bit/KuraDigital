<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManifestoPillar extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id', 'icon', 'title', 'title_sw', 'description', 'description_sw', 'promises', 'sort_order',
    ];

    protected $casts = [
        'promises' => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
