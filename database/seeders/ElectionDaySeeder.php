<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\TallyResult;
use App\Models\User;
use Illuminate\Database\Seeder;

class ElectionDaySeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::first();
        if (!$campaign) {
            return;
        }

        $admin = User::first();
        if (!$admin) {
            return;
        }

        $stations = [
            ['name' => 'Pangani Primary School', 'code' => 'PS-001', 'ward' => 'Pangani', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 1250, 'latitude' => -1.2650, 'longitude' => 36.8360, 'status' => 'closed'],
            ['name' => 'Pumwani Social Hall', 'code' => 'PS-002', 'ward' => 'Pumwani', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 980, 'latitude' => -1.2780, 'longitude' => 36.8520, 'status' => 'closed'],
            ['name' => 'Huruma Community Centre', 'code' => 'PS-003', 'ward' => 'Huruma', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 1100, 'latitude' => -1.2590, 'longitude' => 36.8600, 'status' => 'closed'],
            ['name' => 'Mathare North Primary', 'code' => 'PS-004', 'ward' => 'Mathare North', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 850, 'latitude' => -1.2550, 'longitude' => 36.8580, 'status' => 'open'],
            ['name' => 'Ngara Girls High School', 'code' => 'PS-005', 'ward' => 'Ngara', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 1500, 'latitude' => -1.2740, 'longitude' => 36.8260, 'status' => 'open'],
            ['name' => 'Kariokor Social Hall', 'code' => 'PS-006', 'ward' => 'Kariokor', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 750, 'latitude' => -1.2820, 'longitude' => 36.8340, 'status' => 'open'],
            ['name' => 'Ziwani Community Hall', 'code' => 'PS-007', 'ward' => 'Ziwani', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 620, 'latitude' => -1.2900, 'longitude' => 36.8380, 'status' => 'pending'],
            ['name' => 'Landhies Road Primary', 'code' => 'PS-008', 'ward' => 'Landhies', 'constituency' => 'Starehe', 'county' => 'Nairobi', 'registered_voters' => 900, 'latitude' => -1.2880, 'longitude' => 36.8440, 'status' => 'pending'],
        ];

        $createdStations = [];
        foreach ($stations as $s) {
            $createdStations[] = PollingStation::create(array_merge($s, [
                'campaign_id' => $campaign->id,
                'created_by' => $admin->id,
            ]));
        }

        $candidates = [
            ['name' => 'John Kamau', 'party' => 'Independent'],
            ['name' => 'Mary Atieno', 'party' => 'Unity Alliance'],
            ['name' => 'Peter Maina', 'party' => 'Progressive Party'],
        ];

        // Tallies for closed stations (first 3)
        foreach (array_slice($createdStations, 0, 3) as $i => $station) {
            $totalCast = (int) ($station->registered_voters * (0.55 + ($i * 0.08)));
            $rejected = rand(5, 25);
            $remaining = $totalCast - $rejected;

            $votes = [
                (int) ($remaining * (0.45 + ($i * 0.05))),
                (int) ($remaining * (0.32 - ($i * 0.02))),
                0,
            ];
            $votes[2] = $remaining - $votes[0] - $votes[1];

            foreach ($candidates as $j => $c) {
                TallyResult::create([
                    'campaign_id' => $campaign->id,
                    'polling_station_id' => $station->id,
                    'submitted_by' => $admin->id,
                    'candidate_name' => $c['name'],
                    'party' => $c['party'],
                    'votes' => $votes[$j],
                    'rejected_votes' => $j === 0 ? $rejected : 0,
                    'total_votes_cast' => $totalCast,
                    'status' => $i < 2 ? 'verified' : 'provisional',
                    'verified_by' => $i < 2 ? $admin->id : null,
                    'verified_at' => $i < 2 ? now() : null,
                ]);
            }
        }

        // Incidents
        $incidents = [
            ['title' => 'Delayed ballot delivery', 'description' => 'Ballot papers arrived 2 hours late at Pumwani Social Hall. Voting delayed.', 'category' => 'procedural', 'severity' => 'medium', 'status' => 'resolved', 'polling_station_id' => $createdStations[1]->id, 'ward' => 'Pumwani', 'constituency' => 'Starehe', 'county' => 'Nairobi'],
            ['title' => 'KIEMS kit failure', 'description' => 'Biometric verification device malfunctioned at Pangani Primary. Manual register used.', 'category' => 'equipment_failure', 'severity' => 'high', 'status' => 'acknowledged', 'polling_station_id' => $createdStations[0]->id, 'ward' => 'Pangani', 'constituency' => 'Starehe', 'county' => 'Nairobi'],
            ['title' => 'Campaign agents confrontation', 'description' => 'Heated argument between party agents at Huruma Community Centre. Police intervened.', 'category' => 'violence', 'severity' => 'critical', 'status' => 'escalated', 'polling_station_id' => $createdStations[2]->id, 'ward' => 'Huruma', 'constituency' => 'Starehe', 'county' => 'Nairobi'],
            ['title' => 'Long queues reported', 'description' => 'Over 200 voters still queuing at Ngara Girls at 4pm. IEBC extends closing time.', 'category' => 'procedural', 'severity' => 'low', 'status' => 'reported', 'polling_station_id' => $createdStations[4]->id, 'ward' => 'Ngara', 'constituency' => 'Starehe', 'county' => 'Nairobi'],
        ];

        foreach ($incidents as $inc) {
            $isResolved = $inc['status'] === 'resolved';
            Incident::create(array_merge($inc, [
                'campaign_id' => $campaign->id,
                'reported_by' => $admin->id,
                'resolved_by' => $isResolved ? $admin->id : null,
                'resolved_at' => $isResolved ? now() : null,
                'resolution_notes' => $isResolved ? 'Ballots delivered and voting resumed normally.' : null,
            ]));
        }
    }
}
