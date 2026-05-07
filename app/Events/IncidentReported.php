<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentReported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $campaignId;
    public array $incidentData;

    public function __construct(Incident $incident)
    {
        $this->campaignId = $incident->campaign_id;
        $this->incidentData = [
            'id' => $incident->id,
            'title' => $incident->title,
            'category' => $incident->category,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'ward' => $incident->ward,
            'station_name' => $incident->pollingStation?->name,
            'reported_by' => $incident->reporter?->name,
            'created_at' => $incident->created_at?->toISOString(),
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
        return 'incident.reported';
    }
}
