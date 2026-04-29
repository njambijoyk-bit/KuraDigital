<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\OpponentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentProfileController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $query = $campaign->opponentProfiles()->with('creator:id,name');

        if ($request->filled('threat_level')) {
            $query->where('threat_level', $request->threat_level);
        }
        if ($request->filled('party')) {
            $query->where('party', $request->party);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('party', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        $opponents = $query->orderBy('threat_level', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['opponents' => $opponents]);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'bio' => ['nullable', 'string'],
            'strengths_summary' => ['nullable', 'string'],
            'weaknesses_summary' => ['nullable', 'string'],
            'threat_level' => ['sometimes', 'in:low,medium,high,critical'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $opponent = OpponentProfile::create($validated);
        $opponent->load('creator:id,name');

        return response()->json([
            'message' => 'Opponent profile created.',
            'opponent' => $opponent,
        ], 201);
    }

    public function show(Campaign $campaign, OpponentProfile $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $opponent->load('creator:id,name');
        $opponent->loadCount(['research', 'swotEntries']);

        $swotCounts = $opponent->swotEntries()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        $opponent->setAttribute('swot_summary', [
            'strengths' => $swotCounts->get('strength', 0),
            'weaknesses' => $swotCounts->get('weakness', 0),
            'opportunities' => $swotCounts->get('opportunity', 0),
            'threats' => $swotCounts->get('threat', 0),
        ]);

        return response()->json(['opponent' => $opponent]);
    }

    public function update(Request $request, Campaign $campaign, OpponentProfile $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'constituency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:255'],
            'photo_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'strengths_summary' => ['sometimes', 'nullable', 'string'],
            'weaknesses_summary' => ['sometimes', 'nullable', 'string'],
            'threat_level' => ['sometimes', 'in:low,medium,high,critical'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $opponent->update($validated);

        return response()->json([
            'message' => 'Opponent profile updated.',
            'opponent' => $opponent->fresh()->load('creator:id,name'),
        ]);
    }

    public function destroy(Campaign $campaign, OpponentProfile $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $opponent->delete();

        return response()->json(['message' => 'Opponent profile deleted.']);
    }

    public function overview(Campaign $campaign): JsonResponse
    {
        $opponents = $campaign->opponentProfiles();

        $threatBreakdown = (clone $opponents)
            ->selectRaw('threat_level, count(*) as count')
            ->groupBy('threat_level')
            ->pluck('count', 'threat_level');

        $totalResearch = $campaign->opponentResearch()->count();

        $recentResearch = $campaign->opponentResearch()
            ->with('opponentProfile:id,name', 'creator:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'opponent_profile_id', 'title', 'category', 'clearance_level', 'created_by', 'created_at']);

        return response()->json([
            'overview' => [
                'total_opponents' => $opponents->count(),
                'active_opponents' => (clone $opponents)->where('is_active', true)->count(),
                'threat_breakdown' => [
                    'critical' => $threatBreakdown->get('critical', 0),
                    'high' => $threatBreakdown->get('high', 0),
                    'medium' => $threatBreakdown->get('medium', 0),
                    'low' => $threatBreakdown->get('low', 0),
                ],
                'total_research' => $totalResearch,
                'total_swot_entries' => $campaign->opponentSwotEntries()->count(),
                'recent_research' => $recentResearch,
            ],
        ]);
    }

    public function comparison(Campaign $campaign): JsonResponse
    {
        $opponents = $campaign->opponentProfiles()
            ->where('is_active', true)
            ->withCount(['research', 'swotEntries'])
            ->get(['id', 'name', 'party', 'position', 'threat_level', 'strengths_summary', 'weaknesses_summary']);

        $comparison = $opponents->map(function ($opponent) {
            $swotCounts = $opponent->swotEntries()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type');

            return [
                'id' => $opponent->id,
                'name' => $opponent->name,
                'party' => $opponent->party,
                'position' => $opponent->position,
                'threat_level' => $opponent->threat_level,
                'strengths_summary' => $opponent->strengths_summary,
                'weaknesses_summary' => $opponent->weaknesses_summary,
                'research_count' => $opponent->research_count,
                'swot' => [
                    'strengths' => $swotCounts->get('strength', 0),
                    'weaknesses' => $swotCounts->get('weakness', 0),
                    'opportunities' => $swotCounts->get('opportunity', 0),
                    'threats' => $swotCounts->get('threat', 0),
                ],
            ];
        });

        return response()->json(['comparison' => $comparison]);
    }
}
