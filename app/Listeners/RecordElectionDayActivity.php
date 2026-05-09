<?php

namespace App\Listeners;

use App\Events\IncidentReported;
use App\Events\SituationRoomUpdate;
use App\Events\TallyResultSubmitted;
use App\Events\TallyResultVerified;
use App\Models\ElectionDayActivity;

class RecordElectionDayActivity
{
    public function handle(object $event): void
    {
        $activity = match (true) {
            $event instanceof TallyResultSubmitted => $this->handleTallySubmitted($event),
            $event instanceof TallyResultVerified => $this->handleTallyVerified($event),
            $event instanceof IncidentReported => $this->handleIncidentReported($event),
            default => null,
        };

        if ($activity) {
            event(new SituationRoomUpdate($activity));
        }
    }

    private function handleTallySubmitted(TallyResultSubmitted $event): ElectionDayActivity
    {
        $data = $event->tallyData;

        return ElectionDayActivity::create([
            'campaign_id' => $event->campaignId,
            'type' => 'tally_submitted',
            'severity' => 'info',
            'message' => "New tally: {$data['candidate_name']} — {$data['votes']} votes at {$data['station_name']}",
            'metadata' => [
                'tally_id' => $data['id'],
                'station_id' => $data['polling_station_id'],
                'candidate' => $data['candidate_name'],
                'votes' => $data['votes'],
            ],
        ]);
    }

    private function handleTallyVerified(TallyResultVerified $event): ElectionDayActivity
    {
        $data = $event->tallyData;

        return ElectionDayActivity::create([
            'campaign_id' => $event->campaignId,
            'type' => 'tally_verified',
            'severity' => 'info',
            'message' => "Tally verified: {$data['candidate_name']} at {$data['station_name']} by {$data['verified_by']}",
            'metadata' => [
                'tally_id' => $data['id'],
                'candidate' => $data['candidate_name'],
                'verified_by' => $data['verified_by'],
            ],
        ]);
    }

    private function handleIncidentReported(IncidentReported $event): ElectionDayActivity
    {
        $data = $event->incidentData;
        $severity = in_array($data['severity'], ['critical', 'high']) ? $data['severity'] : 'warning';

        return ElectionDayActivity::create([
            'campaign_id' => $event->campaignId,
            'type' => 'incident_reported',
            'severity' => $severity,
            'message' => strtoupper($data['severity']) . " incident at {$data['station_name']}: {$data['title']}",
            'metadata' => [
                'incident_id' => $data['id'],
                'category' => $data['category'],
                'severity' => $data['severity'],
                'ward' => $data['ward'],
            ],
        ]);
    }
}
