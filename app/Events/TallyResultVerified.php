<?php

namespace App\Events;

use App\Models\TallyResult;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TallyResultVerified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $campaignId;
    public array $tallyData;

    public function __construct(TallyResult $tally)
    {
        $this->campaignId = $tally->campaign_id;
        $this->tallyData = [
            'id' => $tally->id,
            'candidate_name' => $tally->candidate_name,
            'votes' => $tally->votes,
            'status' => $tally->status,
            'station_name' => $tally->pollingStation?->name,
            'verified_by' => $tally->verifier?->name,
            'verified_at' => $tally->verified_at?->toISOString(),
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
        return 'tally.verified';
    }
}
