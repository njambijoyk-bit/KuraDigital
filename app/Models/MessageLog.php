<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    protected $fillable = [
        'message_campaign_id',
        'voter_id',
        'recipient',
        'channel',
        'status',
        'external_id',
        'error',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function messageCampaign(): BelongsTo
    {
        return $this->belongsTo(MessageCampaign::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }
}
