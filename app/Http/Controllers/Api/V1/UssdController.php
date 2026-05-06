<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\FieldAgent;
use App\Models\PollingStation;
use App\Models\TallyResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Africa's Talking USSD callback handler for tally submission.
 *
 * Menu flow:
 *   1. Agent dials USSD shortcode → enters agent code
 *   2. System looks up agent + assigned station
 *   3. Agent selects candidate from list
 *   4. Agent enters vote count
 *   5. Agent confirms submission
 *
 * AT sends POST with: sessionId, phoneNumber, networkCode, serviceCode, text
 * Response format: "CON message" (continue) or "END message" (terminate)
 */
class UssdController extends Controller
{
    public function callback(Request $request): Response
    {
        $sessionId = $request->input('sessionId');
        $phone = $request->input('phoneNumber');
        $text = $request->input('text', '');

        $parts = $text === '' ? [] : explode('*', $text);
        $level = count($parts);

        try {
            $response = match (true) {
                $level === 0 => $this->menuWelcome(),
                $level === 1 => $this->menuLookupAgent($parts[0]),
                $level === 2 => $this->menuSelectCandidate($parts[0], $parts[1]),
                $level === 3 => $this->menuEnterVotes($parts[0], $parts[1], $parts[2]),
                $level === 4 => $this->menuConfirm($parts[0], $parts[1], $parts[2], $parts[3], $phone),
                default => $this->end('Invalid input. Please try again.'),
            };
        } catch (\Throwable $e) {
            Log::error('USSD callback error', [
                'session' => $sessionId,
                'phone' => $phone,
                'text' => $text,
                'error' => $e->getMessage(),
            ]);
            $response = $this->end('An error occurred. Please try again.');
        }

        return response($response, 200)->header('Content-Type', 'text/plain');
    }

    private function menuWelcome(): string
    {
        return $this->con("Welcome to Kura Digital\nEnter your agent code:");
    }

    private function menuLookupAgent(string $agentCode): string
    {
        $agent = FieldAgent::where('agent_code', trim($agentCode))
            ->where('status', 'active')
            ->first();

        if (!$agent) {
            return $this->end("Agent code not found or inactive.\nContact your coordinator.");
        }

        $station = PollingStation::where('campaign_id', $agent->campaign_id)
            ->where('assigned_agent_id', $agent->user_id)
            ->first();

        if (!$station) {
            return $this->end("No polling station assigned to you.\nContact your coordinator.");
        }

        $candidates = $this->getCandidateList($agent->campaign_id, $station->id);

        if (empty($candidates)) {
            return $this->con(
                "Station: {$station->name}\n" .
                "Enter candidate name:"
            );
        }

        $menu = "Station: {$station->name}\nSelect candidate:\n";
        foreach ($candidates as $i => $name) {
            $menu .= ($i + 1) . ". {$name}\n";
        }
        $menu .= "0. Enter new name";

        return $this->con($menu);
    }

    private function menuSelectCandidate(string $agentCode, string $candidateInput): string
    {
        $agent = $this->findAgent($agentCode);
        if (!$agent) {
            return $this->end('Session expired. Dial again.');
        }

        $candidates = $this->getCandidateList($agent->campaign_id, null);

        if ($candidateInput === '0' || empty($candidates)) {
            return $this->con("Enter candidate name:");
        }

        $index = (int) $candidateInput - 1;
        if (!isset($candidates[$index])) {
            return $this->end('Invalid selection.');
        }

        return $this->con("Candidate: {$candidates[$index]}\nEnter number of votes:");
    }

    private function menuEnterVotes(string $agentCode, string $candidateInput, string $candidateName): string
    {
        $agent = $this->findAgent($agentCode);
        if (!$agent) {
            return $this->end('Session expired. Dial again.');
        }

        $candidates = $this->getCandidateList($agent->campaign_id, null);

        if ($candidateInput !== '0' && !empty($candidates)) {
            $index = (int) $candidateInput - 1;
            $name = $candidates[$index] ?? $candidateName;
        } else {
            $name = $candidateName;
        }

        return $this->con("Candidate: {$name}\nEnter number of votes:");
    }

    private function menuConfirm(
        string $agentCode,
        string $candidateInput,
        string $candidateOrVotes,
        string $votesOrConfirm,
        string $phone
    ): string {
        $agent = $this->findAgent($agentCode);
        if (!$agent) {
            return $this->end('Session expired. Dial again.');
        }

        $station = PollingStation::where('campaign_id', $agent->campaign_id)
            ->where('assigned_agent_id', $agent->user_id)
            ->first();

        if (!$station) {
            return $this->end('No station assigned.');
        }

        $candidates = $this->getCandidateList($agent->campaign_id, null);

        if ($candidateInput !== '0' && !empty($candidates)) {
            $index = (int) $candidateInput - 1;
            $candidateName = $candidates[$index] ?? $candidateOrVotes;
            $votes = (int) $candidateOrVotes;
        } else {
            $candidateName = $candidateOrVotes;
            $votes = (int) $votesOrConfirm;
        }

        if ($votes < 0) {
            return $this->end('Invalid vote count.');
        }

        $tally = TallyResult::create([
            'campaign_id' => $agent->campaign_id,
            'polling_station_id' => $station->id,
            'candidate_name' => $candidateName,
            'votes' => $votes,
            'submitted_by' => $agent->user_id,
            'notes' => "Submitted via USSD from {$phone}",
        ]);

        Log::info('USSD tally submitted', [
            'agent_code' => $agentCode,
            'station' => $station->name,
            'candidate' => $candidateName,
            'votes' => $votes,
            'tally_id' => $tally->id,
        ]);

        return $this->end(
            "Tally recorded!\n" .
            "Station: {$station->name}\n" .
            "{$candidateName}: {$votes} votes\n" .
            "Dial again to submit more."
        );
    }

    private function findAgent(string $agentCode): ?FieldAgent
    {
        return FieldAgent::where('agent_code', trim($agentCode))
            ->where('status', 'active')
            ->first();
    }

    private function getCandidateList(int $campaignId, ?int $stationId): array
    {
        $query = TallyResult::where('campaign_id', $campaignId);

        if ($stationId) {
            $query->where('polling_station_id', $stationId);
        }

        return $query->distinct()
            ->pluck('candidate_name')
            ->filter()
            ->values()
            ->take(9)
            ->toArray();
    }

    private function con(string $message): string
    {
        return "CON {$message}";
    }

    private function end(string $message): string
    {
        return "END {$message}";
    }
}
