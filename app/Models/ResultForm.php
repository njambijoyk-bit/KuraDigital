<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'polling_station_id',
        'uploaded_by',
        'form_type',
        'image_path',
        'status',
        'parsed_results',
        'notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'parsed_results' => 'array',
        'verified_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
