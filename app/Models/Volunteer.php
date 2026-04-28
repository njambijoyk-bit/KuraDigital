<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Volunteer extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id', 'name', 'phone', 'email', 'ward', 'role', 'skills',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
