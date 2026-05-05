<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRsvp extends Model
{
    protected $fillable = ['event_id', 'name', 'phone', 'email'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
