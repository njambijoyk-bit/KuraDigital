<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Incident;
use App\Models\PollingStation;
use App\Models\TallyResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $validated['campaign_id'] = $campaign->id;
        $validated['submitted_by'] = $request->user()->id;

        $result = TallyResult::create($validated);

        return response()->json($result->load(['pollingStation:id,name,code', 'submitter:id,name']), 201);
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

        return response()->json($tallyResult->fresh(['pollingStation:id,name,code', 'verifier:id,name']));
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

        return response()->json($incident->load(['pollingStation:id,name,code', 'reporter:id,name']), 201);
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
}
