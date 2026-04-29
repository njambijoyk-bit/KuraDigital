<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Opponent;
use App\Models\OpponentResearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $query = $campaign->opponents();

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
            ->orderByRaw("CASE threat_level WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->paginate(20);

        return response()->json($opponents);
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

    public function show(Campaign $campaign, Opponent $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $opponent->loadCount('research');

        return response()->json(['opponent' => $opponent]);
    }

    public function update(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
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

    public function destroy(Campaign $campaign, Opponent $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $opponent->delete();

        return response()->json(['message' => 'Opponent deleted.']);
    }

    // --- Research sub-resource ---

    public function researchIndex(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $query = $opponent->research()->with('author:id,name');

        if ($request->has('clearance')) {
            $query->where('clearance', $request->input('clearance'));
        }

        if (!$request->user()->can('opponents.view-confidential')) {
            $query->whereNotIn('clearance', ['confidential', 'restricted']);
        }

        $research = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($research);
    }

    public function researchStore(Request $request, Campaign $campaign, Opponent $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'clearance' => ['nullable', 'in:public,internal,confidential,restricted'],
            'source' => ['nullable', 'string', 'max:500'],
            'date_observed' => ['nullable', 'date'],
        ]);

        $validated['opponent_id'] = $opponent->id;
        $validated['created_by'] = $request->user()->id;

        $research = OpponentResearch::create($validated);
        $research->load('author:id,name');

        return response()->json([
            'message' => 'Research note added.',
            'research' => $research,
        ], 201);
    }

    public function researchUpdate(Request $request, Campaign $campaign, Opponent $opponent, OpponentResearch $research): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $research->opponent_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'clearance' => ['sometimes', 'in:public,internal,confidential,restricted'],
            'source' => ['sometimes', 'nullable', 'string', 'max:500'],
            'date_observed' => ['sometimes', 'nullable', 'date'],
        ]);

        $research->update($validated);

        return response()->json([
            'message' => 'Research note updated.',
            'research' => $research->fresh()->load('author:id,name'),
        ]);
    }

    public function researchDestroy(Campaign $campaign, Opponent $opponent, OpponentResearch $research): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $research->opponent_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $research->delete();

        return response()->json(['message' => 'Research note deleted.']);
    }
}
