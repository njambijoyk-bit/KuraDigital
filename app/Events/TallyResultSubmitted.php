<?php

namespace App\Events;

use App\Models\TallyResult;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TallyResultSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $campaignId;
    public array $tallyData;

    public function __construct(TallyResult $tally)
    {
        $this->campaignId = $tally->campaign_id;
        $this->tallyData = [
            'id' => $tally->id,
            'polling_station_id' => $tally->polling_station_id,
            'candidate_name' => $tally->candidate_name,
            'party' => $tally->party,
            'votes' => $tally->votes,
            'rejected_votes' => $tally->rejected_votes,
            'total_votes_cast' => $tally->total_votes_cast,
            'status' => $tally->status,
            'station_name' => $tally->pollingStation?->name,
            'station_code' => $tally->pollingStation?->code,
            'submitted_by' => $tally->submitter?->name,
            'created_at' => $tally->created_at?->toISOString(),
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
        return 'tally.submitted';
    }
}
