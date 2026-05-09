<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\IncidentReported;
use App\Events\TallyResultSubmitted;
use App\Events\TallyResultVerified;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ElectionDayActivity;
use App\Models\FieldAgent;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\ResultForm;
use App\Models\TallyResult;
use App\Services\AfricasTalkingSmsService;
use App\Services\GoogleVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ElectionDayController extends Controller
{
    // =====================================================================
    // Polling Stations
    // =====================================================================

    public function stationsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [PollingStation::class, $campaign]);

        $query = $campaign->pollingStations()->with(['assignedAgent:id,name', 'creator:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }
        if ($request->has('constituency')) {
            $query->where('constituency', $request->input('constituency'));
        }
        if ($request->has('county')) {
            $query->where('county', $request->input('county'));
        }
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('name')->paginate($request->input('per_page', 25)));
    }

    public function stationsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [PollingStation::class, $campaign]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'registered_voters' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'assigned_agent_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $station = PollingStation::create($validated);

        return response()->json($station->load('assignedAgent:id,name'), 201);
    }

    public function stationsShow(Request $request, Campaign $campaign, PollingStation $pollingStation): JsonResponse
    {
        $this->authorize('view', $pollingStation);

        return response()->json(
            $pollingStation->load(['assignedAgent:id,name', 'creator:id,name', 'tallyResults', 'incidents'])
        );
    }

    public function stationsUpdate(Request $request, Campaign $campaign, PollingStation $pollingStation): JsonResponse
    {
        $this->authorize('update', $pollingStation);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'registered_voters' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'string', 'in:pending,open,closed,disputed'],
            'assigned_agent_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $pollingStation->update($validated);

        return response()->json($pollingStation->fresh('assignedAgent:id,name'));
    }

    public function stationsDestroy(Request $request, Campaign $campaign, PollingStation $pollingStation): JsonResponse
    {
        $this->authorize('delete', $pollingStation);

        $pollingStation->delete();

        return response()->json(['message' => 'Polling station deleted.']);
    }

    // =====================================================================
    // Tally Results
    // =====================================================================

    public function talliesIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        $query = $campaign->tallyResults()->with(['pollingStation:id,name,code,ward,constituency', 'submitter:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('polling_station_id')) {
            $query->where('polling_station_id', $request->input('polling_station_id'));
        }
        if ($request->has('candidate_name')) {
            $query->where('candidate_name', $request->input('candidate_name'));
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25)));
    }

    public function talliesStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [TallyResult::class, $campaign]);

        $validated = $request->validate([
            'polling_station_id' => ['required', 'exists:polling_stations,id'],
            'candidate_name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'votes' => ['required', 'integer', 'min:0'],
            'rejected_votes' => ['nullable', 'integer', 'min:0'],
            'total_votes_cast' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo_proof' => ['nullable', 'string', 'max:500'],
        ]);

        $station = PollingStation::where('campaign_id', $campaign->id)
            ->where('id', $validated['polling_station_id'])
            ->firstOrFail();

        if ($station->assigned_agent_id && $station->assigned_agent_id !== $request->user()->id) {
            $isAdmin = $campaign->members()
                ->where('user_id', $request->user()->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();

            if (!$isAdmin) {
                return response()->json([
                    'message' => 'You are not the assigned agent for this polling station.',
                    'assigned_agent_id' => $station->assigned_agent_id,
                ], 403);
            }
        }

        $warnings = $this->detectTallyAnomalies($validated, $station, $campaign);

        $validated['campaign_id'] = $campaign->id;
        $validated['submitted_by'] = $request->user()->id;

        $result = TallyResult::create($validated);
        $result->load(['pollingStation:id,name,code', 'submitter:id,name']);

        event(new TallyResultSubmitted($result));

        $response = $result->toArray();
        if (!empty($warnings)) {
            $response['warnings'] = $warnings;
        }

        return response()->json($response, 201);
    }

    public function talliesShow(Request $request, Campaign $campaign, TallyResult $tallyResult): JsonResponse
    {
        $this->authorize('view', $tallyResult);

        return response()->json(
            $tallyResult->load(['pollingStation:id,name,code,ward,constituency', 'submitter:id,name', 'verifier:id,name'])
        );
    }

    public function talliesUpdate(Request $request, Campaign $campaign, TallyResult $tallyResult): JsonResponse
    {
        $this->authorize('update', $tallyResult);

        if ($tallyResult->status === 'verified') {
            return response()->json(['message' => 'Cannot edit verified results.'], 422);
        }

        $validated = $request->validate([
            'candidate_name' => ['sometimes', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'votes' => ['sometimes', 'integer', 'min:0'],
            'rejected_votes' => ['nullable', 'integer', 'min:0'],
            'total_votes_cast' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo_proof' => ['nullable', 'string', 'max:500'],
        ]);

        $tallyResult->update($validated);

        return response()->json($tallyResult->fresh(['pollingStation:id,name,code', 'submitter:id,name']));
    }

    public function talliesVerify(Request $request, Campaign $campaign, TallyResult $tallyResult): JsonResponse
    {
        $this->authorize('verify', [TallyResult::class, $campaign]);

        if ($tallyResult->status === 'verified') {
            return response()->json(['message' => 'Already verified.'], 422);
        }

        $tallyResult->update([
            'status' => 'verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $tallyResult = $tallyResult->fresh(['pollingStation:id,name,code', 'verifier:id,name']);
        event(new TallyResultVerified($tallyResult));

        return response()->json($tallyResult);
    }

    public function talliesDispute(Request $request, Campaign $campaign, TallyResult $tallyResult): JsonResponse
    {
        $this->authorize('verify', [TallyResult::class, $campaign]);

        $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $tallyResult->update([
            'status' => 'disputed',
            'notes' => $request->input('notes'),
        ]);

        return response()->json($tallyResult->fresh());
    }

    public function talliesDestroy(Request $request, Campaign $campaign, TallyResult $tallyResult): JsonResponse
    {
        $this->authorize('delete', $tallyResult);

        if ($tallyResult->status === 'verified') {
            return response()->json(['message' => 'Cannot delete verified results.'], 422);
        }

        $tallyResult->delete();

        return response()->json(['message' => 'Tally result deleted.']);
    }

    // =====================================================================
    // Tally Aggregation / Command Centre
    // =====================================================================

    public function tallyBoard(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        $results = $campaign->tallyResults()
            ->with('pollingStation:id,name,code,ward,constituency,county,registered_voters')
            ->get();

        $candidateTotals = $results->groupBy('candidate_name')->map(function ($group, $name) {
            return [
                'candidate_name' => $name,
                'party' => $group->first()->party,
                'total_votes' => $group->sum('votes'),
                'stations_reported' => $group->pluck('polling_station_id')->unique()->count(),
                'verified_count' => $group->where('status', 'verified')->count(),
                'provisional_count' => $group->where('status', 'provisional')->count(),
                'disputed_count' => $group->where('status', 'disputed')->count(),
            ];
        })->sortByDesc('total_votes')->values();

        $totalStations = $campaign->pollingStations()->count();
        $reportedStations = $results->pluck('polling_station_id')->unique()->count();
        $totalVotesCast = $results->groupBy('polling_station_id')->map(fn ($g) => $g->max('total_votes_cast'))->sum();
        $totalRegistered = $campaign->pollingStations()->sum('registered_voters');

        return response()->json([
            'candidates' => $candidateTotals,
            'overview' => [
                'total_stations' => $totalStations,
                'reported_stations' => $reportedStations,
                'reporting_percentage' => $totalStations > 0 ? round(($reportedStations / $totalStations) * 100, 1) : 0,
                'total_votes_cast' => $totalVotesCast,
                'total_registered' => $totalRegistered,
                'turnout_percentage' => $totalRegistered > 0 ? round(($totalVotesCast / $totalRegistered) * 100, 1) : 0,
            ],
        ]);
    }

    public function commandCentre(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [PollingStation::class, $campaign]);

        $stations = $campaign->pollingStations()
            ->withCount(['tallyResults', 'incidents'])
            ->get();

        $openStations = $stations->where('status', 'open')->count();
        $closedStations = $stations->where('status', 'closed')->count();
        $disputedStations = $stations->where('status', 'disputed')->count();

        $recentIncidents = $campaign->incidents()
            ->with(['pollingStation:id,name,code', 'reporter:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $incidentsByCategory = $campaign->incidents()
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $incidentsBySeverity = $campaign->incidents()
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $unresolvedIncidents = $campaign->incidents()
            ->whereNotIn('status', ['resolved'])
            ->count();

        return response()->json([
            'stations' => [
                'total' => $stations->count(),
                'open' => $openStations,
                'closed' => $closedStations,
                'disputed' => $disputedStations,
                'pending' => $stations->where('status', 'pending')->count(),
            ],
            'incidents' => [
                'total' => $campaign->incidents()->count(),
                'unresolved' => $unresolvedIncidents,
                'by_category' => $incidentsByCategory,
                'by_severity' => $incidentsBySeverity,
                'recent' => $recentIncidents,
            ],
        ]);
    }

    // =====================================================================
    // Incidents
    // =====================================================================

    public function incidentsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Incident::class, $campaign]);

        $query = $campaign->incidents()->with(['pollingStation:id,name,code', 'reporter:id,name']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->has('polling_station_id')) {
            $query->where('polling_station_id', $request->input('polling_station_id'));
        }
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25)));
    }

    public function incidentsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Incident::class, $campaign]);

        $validated = $request->validate([
            'polling_station_id' => ['nullable', 'exists:polling_stations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'string', 'in:violence,irregularity,voter_intimidation,equipment_failure,procedural,other'],
            'severity' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string', 'max:500'],
        ]);

        if (!empty($validated['polling_station_id'])) {
            PollingStation::where('campaign_id', $campaign->id)
                ->where('id', $validated['polling_station_id'])
                ->firstOrFail();
        }

        $validated['campaign_id'] = $campaign->id;
        $validated['reported_by'] = $request->user()->id;

        $incident = Incident::create($validated);
        $incident->load(['pollingStation:id,name,code', 'reporter:id,name']);

        event(new IncidentReported($incident));

        return response()->json($incident, 201);
    }

    public function incidentsShow(Request $request, Campaign $campaign, Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        return response()->json(
            $incident->load(['pollingStation:id,name,code,ward,constituency', 'reporter:id,name', 'resolver:id,name'])
        );
    }

    public function incidentsUpdate(Request $request, Campaign $campaign, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        if ($incident->status === 'resolved') {
            return response()->json(['message' => 'Cannot edit resolved incidents.'], 422);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'category' => ['sometimes', 'string', 'in:violence,irregularity,voter_intimidation,equipment_failure,procedural,other'],
            'severity' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string', 'max:500'],
        ]);

        $incident->update($validated);

        return response()->json($incident->fresh(['pollingStation:id,name,code', 'reporter:id,name']));
    }

    public function incidentsResolve(Request $request, Campaign $campaign, Incident $incident): JsonResponse
    {
        $this->authorize('resolve', [Incident::class, $campaign]);

        if ($incident->status === 'resolved') {
            return response()->json(['message' => 'Already resolved.'], 422);
        }

        $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        $incident->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes'),
        ]);

        return response()->json($incident->fresh(['pollingStation:id,name,code', 'resolver:id,name']));
    }

    public function incidentsEscalate(Request $request, Campaign $campaign, Incident $incident): JsonResponse
    {
        $this->authorize('resolve', [Incident::class, $campaign]);

        $incident->update([
            'status' => 'escalated',
            'severity' => 'critical',
        ]);

        return response()->json($incident->fresh());
    }

    public function incidentsDestroy(Request $request, Campaign $campaign, Incident $incident): JsonResponse
    {
        $this->authorize('delete', $incident);

        $incident->delete();

        return response()->json(['message' => 'Incident deleted.']);
    }

    // =====================================================================
    // Result Forms (Form 34A/B/C)
    // =====================================================================

    public function formsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        $query = $campaign->resultForms()
            ->with(['pollingStation:id,name,code,ward,constituency', 'uploader:id,name']);

        if ($request->has('form_type')) {
            $query->where('form_type', $request->input('form_type'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('polling_station_id')) {
            $query->where('polling_station_id', $request->input('polling_station_id'));
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25)));
    }

    public function formsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [TallyResult::class, $campaign]);

        $validated = $request->validate([
            'polling_station_id' => ['required', 'exists:polling_stations,id'],
            'form_type' => ['required', 'string', 'in:34A,34B,34C'],
            'image' => ['required', 'image', 'max:10240'],
            'parsed_results' => ['nullable', 'json'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $station = PollingStation::where('campaign_id', $campaign->id)
            ->where('id', $validated['polling_station_id'])
            ->firstOrFail();

        if ($station->assigned_agent_id && $station->assigned_agent_id !== $request->user()->id) {
            $isAdmin = $campaign->members()
                ->where('user_id', $request->user()->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();

            if (!$isAdmin) {
                return response()->json([
                    'message' => 'You are not the assigned agent for this polling station.',
                    'assigned_agent_id' => $station->assigned_agent_id,
                ], 403);
            }
        }

        $path = $request->file('image')->store(
            "campaigns/{$campaign->id}/result-forms",
            'public'
        );

        $parsedResults = $validated['parsed_results']
            ? json_decode($validated['parsed_results'], true)
            : null;

        $ocrResult = null;
        $vision = app(GoogleVisionService::class);
        try {
            $ocrResult = $vision->analyze('public', $path);
        } catch (\Throwable $e) {
            Log::warning('Form 34 OCR failed', ['path' => $path, 'error' => $e->getMessage()]);
        }

        if (!$parsedResults && $ocrResult && $ocrResult['success'] && !empty($ocrResult['ocr_text'])) {
            $parsedResults = $this->parseForm34OcrText($ocrResult['ocr_text'], $validated['form_type']);
        }

        $form = ResultForm::create([
            'campaign_id' => $campaign->id,
            'polling_station_id' => $validated['polling_station_id'],
            'uploaded_by' => $request->user()->id,
            'form_type' => $validated['form_type'],
            'image_path' => $path,
            'parsed_results' => $parsedResults,
            'notes' => $validated['notes'] ?? null,
        ]);

        $response = $form->load(['pollingStation:id,name,code', 'uploader:id,name'])->toArray();
        if ($ocrResult) {
            $response['ocr'] = [
                'success' => $ocrResult['success'],
                'error' => $ocrResult['error'] ?? null,
                'auto_parsed' => !empty($parsedResults) && !$validated['parsed_results'],
            ];
        }

        return response()->json($response, 201);
    }

    public function formsShow(Request $request, Campaign $campaign, ResultForm $resultForm): JsonResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        return response()->json(
            $resultForm->load(['pollingStation:id,name,code,ward,constituency', 'uploader:id,name', 'verifier:id,name'])
        );
    }

    public function formsVerify(Request $request, Campaign $campaign, ResultForm $resultForm): JsonResponse
    {
        $this->authorize('verify', [TallyResult::class, $campaign]);

        if ($resultForm->status === 'verified') {
            return response()->json(['message' => 'Already verified.'], 422);
        }

        $resultForm->update([
            'status' => 'verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return response()->json($resultForm->fresh(['pollingStation:id,name,code', 'verifier:id,name']));
    }

    public function formsDispute(Request $request, Campaign $campaign, ResultForm $resultForm): JsonResponse
    {
        $this->authorize('verify', [TallyResult::class, $campaign]);

        $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $resultForm->update([
            'status' => 'disputed',
            'notes' => $request->input('notes'),
        ]);

        return response()->json($resultForm->fresh());
    }

    public function formsDestroy(Request $request, Campaign $campaign, ResultForm $resultForm): JsonResponse
    {
        $this->authorize('verify', [TallyResult::class, $campaign]);

        if ($resultForm->image_path) {
            Storage::disk('public')->delete($resultForm->image_path);
        }

        $resultForm->delete();

        return response()->json(['message' => 'Result form deleted.']);
    }

    public function formsCompare(Request $request, Campaign $campaign, ResultForm $resultForm): JsonResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        $agentTallies = TallyResult::where('campaign_id', $campaign->id)
            ->where('polling_station_id', $resultForm->polling_station_id)
            ->get(['candidate_name', 'party', 'votes', 'rejected_votes', 'total_votes_cast', 'status']);

        return response()->json([
            'form' => $resultForm->load(['pollingStation:id,name,code', 'uploader:id,name']),
            'agent_tallies' => $agentTallies,
            'discrepancies' => $this->findDiscrepancies($resultForm, $agentTallies),
        ]);
    }

    private function findDiscrepancies(ResultForm $form, $agentTallies): array
    {
        if (!$form->parsed_results || !count($agentTallies)) {
            return [];
        }

        $discrepancies = [];
        $parsed = collect($form->parsed_results);

        foreach ($agentTallies as $tally) {
            $formEntry = $parsed->firstWhere('candidate_name', $tally->candidate_name);
            if (!$formEntry) {
                $discrepancies[] = [
                    'type' => 'missing_in_form',
                    'candidate' => $tally->candidate_name,
                    'agent_votes' => $tally->votes,
                ];
                continue;
            }
            if (isset($formEntry['votes']) && (int) $formEntry['votes'] !== $tally->votes) {
                $discrepancies[] = [
                    'type' => 'vote_mismatch',
                    'candidate' => $tally->candidate_name,
                    'form_votes' => (int) $formEntry['votes'],
                    'agent_votes' => $tally->votes,
                    'difference' => abs((int) $formEntry['votes'] - $tally->votes),
                ];
            }
        }

        foreach ($parsed as $entry) {
            $match = $agentTallies->firstWhere('candidate_name', $entry['candidate_name'] ?? null);
            if (!$match && isset($entry['candidate_name'])) {
                $discrepancies[] = [
                    'type' => 'missing_in_agent',
                    'candidate' => $entry['candidate_name'],
                    'form_votes' => $entry['votes'] ?? 0,
                ];
            }
        }

        return $discrepancies;
    }

    // =====================================================================
    // Anomaly Detection
    // =====================================================================

    private function detectTallyAnomalies(array $tally, PollingStation $station, Campaign $campaign): array
    {
        $warnings = [];

        if ($station->registered_voters && $station->registered_voters > 0) {
            if ($tally['votes'] > $station->registered_voters) {
                $warnings[] = [
                    'type' => 'votes_exceed_registered',
                    'message' => "Votes ({$tally['votes']}) exceed registered voters ({$station->registered_voters}) at {$station->name}.",
                ];
            }
            if (!empty($tally['total_votes_cast']) && $tally['total_votes_cast'] > $station->registered_voters) {
                $warnings[] = [
                    'type' => 'turnout_exceeds_100',
                    'message' => "Total votes cast ({$tally['total_votes_cast']}) exceed registered voters ({$station->registered_voters}).",
                ];
            }
        }

        $existingTally = TallyResult::where('campaign_id', $campaign->id)
            ->where('polling_station_id', $station->id)
            ->where('candidate_name', $tally['candidate_name'])
            ->first();

        if ($existingTally) {
            $warnings[] = [
                'type' => 'duplicate_candidate',
                'message' => "A tally for '{$tally['candidate_name']}' at {$station->name} already exists (ID: {$existingTally->id}).",
            ];
        }

        $neighborStations = PollingStation::where('campaign_id', $campaign->id)
            ->where('id', '!=', $station->id)
            ->where('ward', $station->ward)
            ->pluck('id');

        if ($neighborStations->isNotEmpty() && $station->registered_voters > 0) {
            $wardAvgTurnout = TallyResult::whereIn('polling_station_id', $neighborStations)
                ->avg('total_votes_cast');

            if ($wardAvgTurnout && $wardAvgTurnout > 0 && !empty($tally['total_votes_cast'])) {
                $ratio = $tally['total_votes_cast'] / $wardAvgTurnout;
                if ($ratio > 2.0) {
                    $warnings[] = [
                        'type' => 'unusual_turnout_vs_ward',
                        'message' => "Turnout is {$ratio}x the ward average — significantly higher than neighboring stations.",
                    ];
                }
            }
        }

        return $warnings;
    }

    // =====================================================================
    // Election Day Summary / Readiness
    // =====================================================================

    public function summary(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        $stations = $campaign->pollingStations()
            ->withCount(['tallyResults', 'incidents'])
            ->get();

        $totalStations = $stations->count();
        $reportedStations = $stations->where('tally_results_count', '>', 0)->count();
        $stationsWithIncidents = $stations->where('incidents_count', '>', 0)->count();

        $stationsByStatus = $stations->groupBy('status')->map->count();

        $tallies = $campaign->tallyResults()->get();
        $totalVotesCast = $tallies->groupBy('polling_station_id')
            ->map(fn ($g) => $g->max('total_votes_cast'))
            ->sum();
        $totalRegistered = $stations->sum('registered_voters');

        $verifiedTallies = $tallies->where('status', 'verified')->count();
        $provisionalTallies = $tallies->where('status', 'provisional')->count();
        $disputedTallies = $tallies->where('status', 'disputed')->count();

        $unresolved = $campaign->incidents()->where('status', '!=', 'resolved')->count();
        $criticalIncidents = $campaign->incidents()->where('severity', 'critical')->where('status', '!=', 'resolved')->count();

        $agents = $campaign->fieldAgents()->get();
        $totalAgents = $agents->count();
        $activeAgents = $agents->where('status', 'active')->count();

        $stationsWithoutAgents = $stations->whereNull('assigned_agent_id')->count();

        $turnoutByWard = $stations->groupBy('ward')->map(function ($wardStations) use ($tallies) {
            $registered = $wardStations->sum('registered_voters');
            $stationIds = $wardStations->pluck('id');
            $cast = $tallies->whereIn('polling_station_id', $stationIds)
                ->groupBy('polling_station_id')
                ->map(fn ($g) => $g->max('total_votes_cast'))
                ->sum();
            return [
                'stations' => $wardStations->count(),
                'registered' => $registered,
                'votes_cast' => $cast,
                'turnout' => $registered > 0 ? round(($cast / $registered) * 100, 1) : 0,
                'reported' => $wardStations->where('tally_results_count', '>', 0)->count(),
            ];
        })->sortByDesc('turnout')->values();

        return response()->json([
            'reporting' => [
                'total_stations' => $totalStations,
                'reported' => $reportedStations,
                'unreported' => $totalStations - $reportedStations,
                'reporting_percentage' => $totalStations > 0 ? round(($reportedStations / $totalStations) * 100, 1) : 0,
            ],
            'turnout' => [
                'total_registered' => $totalRegistered,
                'total_votes_cast' => $totalVotesCast,
                'turnout_percentage' => $totalRegistered > 0 ? round(($totalVotesCast / $totalRegistered) * 100, 1) : 0,
            ],
            'tallies' => [
                'verified' => $verifiedTallies,
                'provisional' => $provisionalTallies,
                'disputed' => $disputedTallies,
            ],
            'stations_by_status' => $stationsByStatus,
            'incidents' => [
                'unresolved' => $unresolved,
                'critical' => $criticalIncidents,
                'stations_with_incidents' => $stationsWithIncidents,
            ],
            'agents' => [
                'total' => $totalAgents,
                'active' => $activeAgents,
                'stations_without_agent' => $stationsWithoutAgents,
            ],
            'turnout_by_ward' => $turnoutByWard,
        ]);
    }

    // =====================================================================
    // Agent Deployment Status
    // =====================================================================

    public function agentDeployment(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [PollingStation::class, $campaign]);

        $agents = $campaign->fieldAgents()
            ->with(['user:id,name,email', 'schedules' => function ($q) {
                $q->whereDate('shift_date', now()->toDateString());
            }])
            ->get();

        $stations = $campaign->pollingStations()
            ->select('id', 'name', 'code', 'ward', 'assigned_agent_id', 'status')
            ->get();

        $checkedInToday = $campaign->checkIns()
            ->whereDate('created_at', now()->toDateString())
            ->pluck('user_id')
            ->unique();

        $deployed = [];
        $undeployed = [];

        foreach ($agents as $agent) {
            $agentData = [
                'id' => $agent->id,
                'name' => $agent->user?->name ?? 'Unknown',
                'agent_code' => $agent->agent_code,
                'phone' => $agent->phone,
                'ward' => $agent->ward,
                'status' => $agent->status,
                'checked_in_today' => $checkedInToday->contains($agent->user_id),
                'scheduled_today' => $agent->schedules->isNotEmpty(),
                'assigned_station' => $stations->where('assigned_agent_id', $agent->user_id)->first()?->name,
            ];

            if ($checkedInToday->contains($agent->user_id)) {
                $deployed[] = $agentData;
            } else {
                $undeployed[] = $agentData;
            }
        }

        $unmanned = $stations->whereNull('assigned_agent_id')
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'code' => $s->code, 'ward' => $s->ward])
            ->values();

        return response()->json([
            'overview' => [
                'total_agents' => $agents->count(),
                'checked_in' => count($deployed),
                'not_checked_in' => count($undeployed),
                'unmanned_stations' => $unmanned->count(),
            ],
            'deployed' => $deployed,
            'undeployed' => $undeployed,
            'unmanned_stations' => $unmanned,
        ]);
    }

    // =====================================================================
    // Situation Room (unified live dashboard)
    // =====================================================================

    public function situationRoom(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        // --- Tally Board ---
        $tallies = $campaign->tallyResults()
            ->with('pollingStation:id,name,code,ward,constituency,county,registered_voters')
            ->get();

        $candidateTotals = $tallies->groupBy('candidate_name')->map(function ($group, $name) {
            return [
                'candidate_name' => $name,
                'party' => $group->first()->party,
                'total_votes' => $group->sum('votes'),
                'stations_reported' => $group->pluck('polling_station_id')->unique()->count(),
                'verified_count' => $group->where('status', 'verified')->count(),
            ];
        })->sortByDesc('total_votes')->values();

        $stations = $campaign->pollingStations()
            ->withCount(['tallyResults', 'incidents'])
            ->get();

        $totalStations = $stations->count();
        $reportedStations = $tallies->pluck('polling_station_id')->unique()->count();
        $totalVotesCast = $tallies->groupBy('polling_station_id')
            ->map(fn ($g) => $g->max('total_votes_cast'))
            ->sum();
        $totalRegistered = $stations->sum('registered_voters');

        // --- Incidents ---
        $unresolvedIncidents = $campaign->incidents()
            ->whereNotIn('status', ['resolved'])
            ->count();
        $criticalIncidents = $campaign->incidents()
            ->where('severity', 'critical')
            ->where('status', '!=', 'resolved')
            ->count();

        $recentIncidents = $campaign->incidents()
            ->with(['pollingStation:id,name,code', 'reporter:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->title,
                'category' => $i->category,
                'severity' => $i->severity,
                'status' => $i->status,
                'ward' => $i->ward,
                'station_name' => $i->pollingStation?->name,
                'reported_by' => $i->reporter?->name,
                'created_at' => $i->created_at?->toISOString(),
            ]);

        // --- Agents ---
        $agents = $campaign->fieldAgents()->with('user:id,name')->get();
        $totalAgents = $agents->count();
        $activeAgents = $agents->where('status', 'active')->count();

        $checkedInToday = $campaign->checkIns()
            ->whereDate('created_at', now()->toDateString())
            ->pluck('user_id')
            ->unique();

        $stationsWithoutAgents = $stations->whereNull('assigned_agent_id')->count();

        // Agent locations (latest check-in per agent)
        $agentLocations = $campaign->checkIns()
            ->with('user:id,name')
            ->whereIn('user_id', $agents->pluck('user_id'))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->unique('user_id')
            ->map(fn ($c) => [
                'user_id' => $c->user_id,
                'name' => $c->user?->name,
                'latitude' => $c->latitude,
                'longitude' => $c->longitude,
                'checked_in_at' => $c->created_at?->toISOString(),
            ])
            ->values();

        // --- Station locations for map ---
        $stationLocations = $stations
            ->filter(fn ($s) => $s->latitude && $s->longitude)
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'status' => $s->status,
                'ward' => $s->ward,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'has_tallies' => $s->tally_results_count > 0,
                'has_incidents' => $s->incidents_count > 0,
            ])
            ->values();

        // --- Incident locations for map ---
        $incidentLocations = $campaign->incidents()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'resolved')
            ->select('id', 'title', 'severity', 'category', 'latitude', 'longitude', 'created_at')
            ->limit(100)
            ->get();

        // --- Activity stream ---
        $activities = ElectionDayActivity::where('campaign_id', $campaign->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'severity' => $a->severity,
                'message' => $a->message,
                'metadata' => $a->metadata,
                'time' => $a->created_at?->toISOString(),
            ]);

        // --- Turnout by ward ---
        $turnoutByWard = $stations->groupBy('ward')->map(function ($wardStations) use ($tallies) {
            $registered = $wardStations->sum('registered_voters');
            $stationIds = $wardStations->pluck('id');
            $cast = $tallies->whereIn('polling_station_id', $stationIds)
                ->groupBy('polling_station_id')
                ->map(fn ($g) => $g->max('total_votes_cast'))
                ->sum();
            return [
                'ward' => $wardStations->first()->ward,
                'stations' => $wardStations->count(),
                'registered' => $registered,
                'votes_cast' => $cast,
                'turnout' => $registered > 0 ? round(($cast / $registered) * 100, 1) : 0,
                'reported' => $wardStations->where('tally_results_count', '>', 0)->count(),
            ];
        })->sortByDesc('turnout')->values();

        return response()->json([
            'tally_board' => [
                'candidates' => $candidateTotals,
                'overview' => [
                    'total_stations' => $totalStations,
                    'reported_stations' => $reportedStations,
                    'reporting_percentage' => $totalStations > 0 ? round(($reportedStations / $totalStations) * 100, 1) : 0,
                    'total_votes_cast' => $totalVotesCast,
                    'total_registered' => $totalRegistered,
                    'turnout_percentage' => $totalRegistered > 0 ? round(($totalVotesCast / $totalRegistered) * 100, 1) : 0,
                ],
            ],
            'incidents' => [
                'unresolved' => $unresolvedIncidents,
                'critical' => $criticalIncidents,
                'recent' => $recentIncidents,
            ],
            'agents' => [
                'total' => $totalAgents,
                'active' => $activeAgents,
                'checked_in' => $checkedInToday->count(),
                'unmanned_stations' => $stationsWithoutAgents,
                'locations' => $agentLocations,
            ],
            'map' => [
                'stations' => $stationLocations,
                'incidents' => $incidentLocations,
            ],
            'tallies' => [
                'verified' => $tallies->where('status', 'verified')->count(),
                'provisional' => $tallies->where('status', 'provisional')->count(),
                'disputed' => $tallies->where('status', 'disputed')->count(),
            ],
            'turnout_by_ward' => $turnoutByWard,
            'activity_stream' => $activities,
            'generated_at' => now()->toISOString(),
        ]);
    }

    // =====================================================================
    // SMS Alerts for Critical Incidents
    // =====================================================================

    public function incidentsStoreWithAlert(Request $request, Campaign $campaign): JsonResponse
    {
        $response = $this->incidentsStore($request, $campaign);

        if ($response->getStatusCode() === 201) {
            $incident = json_decode($response->getContent(), true);
            $severity = $incident['severity'] ?? 'low';

            if (in_array($severity, ['critical', 'high'])) {
                $this->sendIncidentSmsAlert($campaign, $incident);
            }
        }

        return $response;
    }

    private function sendIncidentSmsAlert(Campaign $campaign, array $incident): void
    {
        $sms = app(AfricasTalkingSmsService::class);
        if (!$sms->isConfigured()) {
            return;
        }

        $stationName = $incident['polling_station']['name'] ?? ($incident['ward'] ?? 'Unknown location');
        $message = "[KURA ALERT] {$incident['severity']} incident at {$stationName}: {$incident['title']}. Reported by {$incident['reporter']['name']}.";

        $coordinators = $campaign->members()
            ->where('is_active', true)
            ->whereIn('role', ['owner', 'admin', 'coordinator'])
            ->with('user:id,phone')
            ->get();

        foreach ($coordinators as $member) {
            $phone = $member->user?->phone;
            if ($phone) {
                try {
                    $sms->send($phone, $message);
                } catch (\Exception $e) {
                    Log::warning('Failed to send incident SMS alert', [
                        'phone' => $phone,
                        'incident_id' => $incident['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    // =====================================================================
    // CSV Export
    // =====================================================================

    public function talliesExportCsv(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorize('viewAny', [TallyResult::class, $campaign]);

        $tallies = $campaign->tallyResults()
            ->with(['pollingStation:id,name,code,ward,constituency,county,registered_voters', 'submitter:id,name', 'verifier:id,name'])
            ->orderBy('polling_station_id')
            ->orderBy('candidate_name')
            ->get();

        $filename = 'tallies_' . $campaign->id . '_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($tallies) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Station', 'Station Code', 'Ward', 'Constituency', 'County',
                'Registered Voters', 'Candidate', 'Party', 'Votes',
                'Rejected Votes', 'Total Cast', 'Status',
                'Submitted By', 'Verified By', 'Created At',
            ]);

            foreach ($tallies as $tally) {
                fputcsv($handle, [
                    $tally->pollingStation?->name ?? '',
                    $tally->pollingStation?->code ?? '',
                    $tally->pollingStation?->ward ?? '',
                    $tally->pollingStation?->constituency ?? '',
                    $tally->pollingStation?->county ?? '',
                    $tally->pollingStation?->registered_voters ?? '',
                    $tally->candidate_name,
                    $tally->party ?? '',
                    $tally->votes,
                    $tally->rejected_votes ?? '',
                    $tally->total_votes_cast ?? '',
                    $tally->status,
                    $tally->submitter?->name ?? '',
                    $tally->verifier?->name ?? '',
                    $tally->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function incidentsExportCsv(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorize('viewAny', [Incident::class, $campaign]);

        $incidents = $campaign->incidents()
            ->with(['pollingStation:id,name,code', 'reporter:id,name', 'resolver:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'incidents_' . $campaign->id . '_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($incidents) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Title', 'Category', 'Severity', 'Status',
                'Station', 'Ward', 'Constituency', 'County',
                'Reported By', 'Resolved By', 'Created At', 'Resolved At',
            ]);

            foreach ($incidents as $incident) {
                fputcsv($handle, [
                    $incident->id,
                    $incident->title,
                    $incident->category,
                    $incident->severity,
                    $incident->status,
                    $incident->pollingStation?->name ?? '',
                    $incident->ward ?? '',
                    $incident->constituency ?? '',
                    $incident->county ?? '',
                    $incident->reporter?->name ?? '',
                    $incident->resolver?->name ?? '',
                    $incident->created_at?->toDateTimeString(),
                    $incident->resolved_at?->toDateTimeString() ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // =====================================================================
    // Form 34 OCR Text Parser
    // =====================================================================

    private function parseForm34OcrText(string $ocrText, string $formType): ?array
    {
        $lines = preg_split('/\r?\n/', trim($ocrText));
        $candidates = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (preg_match('/^(.+?)\s+(\d{1,3}(?:,\d{3})*)\s*$/', $line, $matches)) {
                $name = trim($matches[1]);
                $votes = (int) str_replace(',', '', $matches[2]);

                if (strlen($name) >= 2 && $votes >= 0) {
                    $candidates[] = [
                        'candidate_name' => $name,
                        'votes' => $votes,
                    ];
                }
                continue;
            }

            if (preg_match('/^(.+?)\s*[-:|]\s*(\d{1,3}(?:,\d{3})*)\s*$/', $line, $matches)) {
                $name = trim($matches[1]);
                $votes = (int) str_replace(',', '', $matches[2]);

                if (strlen($name) >= 2 && $votes >= 0) {
                    $candidates[] = [
                        'candidate_name' => $name,
                        'votes' => $votes,
                    ];
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        $totalVotes = array_sum(array_column($candidates, 'votes'));
        $rejectedVotes = null;

        if (preg_match('/rejected\s*(?:votes?)?\s*[-:|]?\s*(\d+)/i', $ocrText, $rejMatch)) {
            $rejectedVotes = (int) $rejMatch[1];
        }

        return [
            'form_type' => $formType,
            'candidates' => $candidates,
            'total_votes' => $totalVotes,
            'rejected_votes' => $rejectedVotes,
            'ocr_confidence' => 'low',
            'requires_verification' => true,
        ];
    }

    // =====================================================================
    // IEBC Polling Station Import (API)
    // =====================================================================

    public function importIebcStations(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [PollingStation::class, $campaign]);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'skip_duplicates' => ['nullable', 'boolean'],
        ]);

        $skipDuplicates = $request->boolean('skip_duplicates', false);
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            return response()->json(['message' => 'Empty CSV file.'], 422);
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        if (!in_array('name', $header)) {
            fclose($handle);
            return response()->json([
                'message' => "Required column 'name' not found.",
                'available_columns' => $header,
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($header)) {
                $errors++;
                continue;
            }

            $record = array_combine($header, $data);
            $name = trim($record['name'] ?? '');
            $code = trim($record['code'] ?? '');

            if (empty($name)) {
                $errors++;
                continue;
            }

            $stationData = [
                'campaign_id' => $campaign->id,
                'created_by' => $request->user()->id,
                'name' => $name,
                'code' => $code ?: null,
                'ward' => trim($record['ward'] ?? '') ?: null,
                'constituency' => trim($record['constituency'] ?? '') ?: null,
                'county' => trim($record['county'] ?? '') ?: null,
                'registered_voters' => (int) ($record['registered_voters'] ?? 0),
                'latitude' => !empty($record['latitude']) ? (float) $record['latitude'] : null,
                'longitude' => !empty($record['longitude']) ? (float) $record['longitude'] : null,
                'status' => 'pending',
            ];

            $existing = $code
                ? PollingStation::where('campaign_id', $campaign->id)->where('code', $code)->first()
                : null;

            if ($existing) {
                if ($skipDuplicates) {
                    $skipped++;
                    continue;
                }
                $existing->update($stationData);
                $updated++;
            } else {
                PollingStation::create($stationData);
                $created++;
            }
        }

        fclose($handle);

        return response()->json([
            'message' => 'IEBC station import complete.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }
}
