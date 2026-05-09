<?php

namespace App\Events;

use App\Models\ElectionDayActivity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SituationRoomUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $campaignId;
    public array $activity;

    public function __construct(ElectionDayActivity $activity)
    {
        $this->campaignId = $activity->campaign_id;
        $this->activity = [
            'id' => $activity->id,
            'type' => $activity->type,
            'severity' => $activity->severity,
            'message' => $activity->message,
            'metadata' => $activity->metadata,
            'time' => $activity->created_at?->toISOString(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("election-day.{$this->campaignId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'situation-room.update';
    }
}
