<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\FieldReport;
use App\Models\Opponent;
use App\Models\OpponentResearch;
use App\Models\VoterInteraction;
use App\Services\SentimentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Opponent::class, $campaign]);

        $query = $campaign->opponents();

        // Apply geographic ABAC filters
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('threat_level')) {
            $query->where('threat_level', $request->input('threat_level'));
        }

        if ($request->has('party')) {
            $query->where('party', $request->input('party'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('party', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $opponents = $query->withCount('research')
            ->orderByRaw("CASE WHEN threat_level = 'critical' THEN 1 WHEN threat_level = 'high' THEN 2 WHEN threat_level = 'medium' THEN 3 WHEN threat_level = 'low' THEN 4 ELSE 5 END")
            ->paginate(20);

        return response()->json($opponents);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Opponent::class, $campaign]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'threat_level' => ['nullable', 'in:low,medium,high,critical'],
            'bio' => ['nullable', 'string'],
            'strengths' => ['nullable', 'string'],
            'weaknesses' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'max:500'],
            'social_facebook' => ['nullable', 'string', 'max:500'],
            'social_twitter' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['campaign_id'] = $campaign->id;

        $opponent = Opponent::create($validated);

        return response()->json([
            'message' => 'Opponent added.',
            'opponent' => $opponent,
        ], 201);
    }

    public function show(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        $this->authorize('viewAny', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $opponent->loadCount('research');

        return response()->json(['opponent' => $opponent]);
    }

    public function update(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        $this->authorize('update', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'constituency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:255'],
            'threat_level' => ['sometimes', 'in:low,medium,high,critical'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'strengths' => ['sometimes', 'nullable', 'string'],
            'weaknesses' => ['sometimes', 'nullable', 'string'],
            'photo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'social_facebook' => ['sometimes', 'nullable', 'string', 'max:500'],
            'social_twitter' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $opponent->update($validated);

        return response()->json([
            'message' => 'Opponent updated.',
            'opponent' => $opponent->fresh(),
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        $this->authorize('delete', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $opponent->delete();

        return response()->json(['message' => 'Opponent deleted.']);
    }

    // --- Research sub-resource ---

    public function researchIndex(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        $this->authorize('viewResearch', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $query = $opponent->research()->with('author:id,name');

        if ($request->has('clearance')) {
            $query->where('clearance', $request->input('clearance'));
        }

        // ABAC: filter research by user's clearance level
        $user = $request->user();
        if (!$user->hasClearance('top_secret')) {
            if ($user->hasClearance('confidential')) {
                $query->whereNotIn('clearance', ['restricted']);
            } else {
                $query->whereNotIn('clearance', ['confidential', 'restricted']);
            }
        }

        $research = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($research);
    }

    public function researchStore(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        $this->authorize('addResearch', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'clearance' => ['nullable', 'in:public,internal,confidential,restricted'],
            'source' => ['nullable', 'string', 'max:500'],
            'date_observed' => ['nullable', 'date'],
        ]);

        if (isset($validated['clearance']) && !$request->user()->hasClearance($validated['clearance'])) {
            return response()->json(['message' => 'Cannot classify above your clearance level.'], 403);
        }

        $validated['opponent_id'] = $opponent->id;
        $validated['created_by'] = $request->user()->id;

        // Auto-analyze sentiment
        $sentiment = app(SentimentAnalysisService::class)->analyze(
            ($validated['title'] ?? '') . ' ' . ($validated['content'] ?? '')
        );
        $validated['sentiment_score'] = $sentiment['score'];
        $validated['sentiment_label'] = $sentiment['label'];

        $research = OpponentResearch::create($validated);
        $research->load('author:id,name');

        return response()->json([
            'message' => 'Research note added.',
            'research' => $research,
        ], 201);
    }

    public function researchUpdate(Request $request, Campaign $campaign, Opponent $opponent, OpponentResearch $research): JsonResponse
    {
        $this->authorize('editResearch', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id || $research->opponent_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'clearance' => ['sometimes', 'in:public,internal,confidential,restricted'],
            'source' => ['sometimes', 'nullable', 'string', 'max:500'],
            'date_observed' => ['sometimes', 'nullable', 'date'],
        ]);

        if (isset($validated['clearance']) && !$request->user()->hasClearance($validated['clearance'])) {
            return response()->json(['message' => 'Cannot classify above your clearance level.'], 403);
        }

        $research->update($validated);

        // Re-analyze sentiment if title or content changed
        if (isset($validated['title']) || isset($validated['content'])) {
            $fresh = $research->fresh();
            $sentiment = app(SentimentAnalysisService::class)->analyze(
                $fresh->title . ' ' . $fresh->content
            );
            $fresh->update([
                'sentiment_score' => $sentiment['score'],
                'sentiment_label' => $sentiment['label'],
            ]);
        }

        return response()->json([
            'message' => 'Research note updated.',
            'research' => $research->fresh()->load('author:id,name'),
        ]);
    }

    public function researchDestroy(Request $request, Campaign $campaign, Opponent $opponent, OpponentResearch $research): JsonResponse
    {
        $this->authorize('deleteResearch', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id || $research->opponent_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $research->delete();

        return response()->json(['message' => 'Research note deleted.']);
    }

    // --- Sentiment Analysis ---

    public function researchReanalyze(Request $request, Campaign $campaign, Opponent $opponent, OpponentResearch $research): JsonResponse
    {
        $this->authorize('editResearch', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id || $research->opponent_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $sentiment = app(SentimentAnalysisService::class)->analyze(
            $research->title . ' ' . $research->content
        );

        $research->update([
            'sentiment_score' => $sentiment['score'],
            'sentiment_label' => $sentiment['label'],
        ]);

        return response()->json([
            'message' => 'Sentiment re-analyzed.',
            'research' => $research->fresh()->load('author:id,name'),
        ]);
    }

    public function sentimentSummary(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        $this->authorize('viewResearch', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($opponent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        // Research note sentiment aggregation
        $researchSentiment = $opponent->research()
            ->whereNotNull('sentiment_label')
            ->selectRaw('sentiment_label, COUNT(*) as count, AVG(sentiment_score) as avg_score')
            ->groupBy('sentiment_label')
            ->get()
            ->keyBy('sentiment_label');

        // Research sentiment over time (monthly)
        $driver = $opponent->research()->getQuery()->getConnection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $researchTimeline = $opponent->research()
            ->whereNotNull('sentiment_score')
            ->selectRaw("{$monthExpr} as month, AVG(sentiment_score) as avg_score, COUNT(*) as count")
            ->groupByRaw("{$monthExpr}")
            ->orderBy('month')
            ->get();

        // Scan field reports for mentions
        $service = app(SentimentAnalysisService::class);
        $opponentData = [['id' => $opponent->id, 'name' => $opponent->name]];

        $fieldReportMentions = [];
        FieldReport::where('campaign_id', $campaign->id)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderByDesc('created_at')
            ->limit(500)
            ->chunk(100, function ($reports) use ($service, $opponentData, &$fieldReportMentions) {
                foreach ($reports as $report) {
                    $mentions = $service->scanForOpponentMentions($report->body, $opponentData);
                    if (!empty($mentions)) {
                        $fieldReportMentions[] = [
                            'id' => $report->id,
                            'title' => $report->title,
                            'type' => $report->type,
                            'date' => $report->created_at->toDateString(),
                            'sentiment_score' => $mentions[0]['score'],
                            'sentiment_label' => $mentions[0]['label'],
                            'excerpt' => mb_substr($report->body, 0, 200),
                        ];
                    }
                }
            });

        // Scan voter interactions for mentions
        $voterInteractionMentions = [];
        VoterInteraction::where('campaign_id', $campaign->id)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderByDesc('created_at')
            ->limit(500)
            ->chunk(100, function ($interactions) use ($service, $opponentData, &$voterInteractionMentions) {
                foreach ($interactions as $interaction) {
                    $mentions = $service->scanForOpponentMentions($interaction->notes, $opponentData);
                    if (!empty($mentions)) {
                        $voterInteractionMentions[] = [
                            'id' => $interaction->id,
                            'type' => $interaction->interaction_type,
                            'outcome' => $interaction->outcome,
                            'date' => $interaction->created_at->toDateString(),
                            'sentiment_score' => $mentions[0]['score'],
                            'sentiment_label' => $mentions[0]['label'],
                            'excerpt' => mb_substr($interaction->notes, 0, 200),
                        ];
                    }
                }
            });

        // Compute overall aggregates
        $allScores = collect($fieldReportMentions)->pluck('sentiment_score')
            ->merge(collect($voterInteractionMentions)->pluck('sentiment_score'))
            ->merge($opponent->research()->whereNotNull('sentiment_score')->pluck('sentiment_score'));

        $overallAvg = $allScores->isNotEmpty() ? round($allScores->avg(), 2) : 0;
        $overallLabel = $overallAvg >= 0.3 ? 'positive' : ($overallAvg <= -0.3 ? 'negative' : 'neutral');

        return response()->json([
            'overall' => [
                'avg_score' => $overallAvg,
                'label' => $overallLabel,
                'total_sources' => $allScores->count(),
            ],
            'research_breakdown' => $researchSentiment,
            'research_timeline' => $researchTimeline,
            'field_report_mentions' => $fieldReportMentions,
            'voter_interaction_mentions' => $voterInteractionMentions,
        ]);
    }

    public function bulkReanalyze(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        $this->authorize('editResearch', [Opponent::class, $campaign]);

        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $service = app(SentimentAnalysisService::class);
        $count = 0;

        $opponent->research()->chunk(50, function ($notes) use ($service, &$count) {
            foreach ($notes as $note) {
                $sentiment = $service->analyze($note->title . ' ' . $note->content);
                $note->update([
                    'sentiment_score' => $sentiment['score'],
                    'sentiment_label' => $sentiment['label'],
                ]);
                $count++;
            }
        });

        return response()->json([
            'message' => "Re-analyzed sentiment for {$count} research notes.",
            'count' => $count,
        ]);
    }
}
