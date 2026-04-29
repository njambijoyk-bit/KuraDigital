<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\OpponentProfile;
use App\Models\OpponentResearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentResearchController extends Controller
{
    public function index(Request $request, Campaign $campaign, OpponentProfile $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $query = $opponent->research()->with('creator:id,name');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('clearance_level')) {
            $query->where('clearance_level', $request->clearance_level);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('source_name', 'like', "%{$search}%");
            });
        }

        // Enforce clearance: hide confidential/restricted unless user has permission
        if (!$request->user()->can('opponents.view-confidential')) {
            $query->whereIn('clearance_level', ['public', 'internal']);
        }

        $research = $query->orderByDesc('created_at')->get();

        return response()->json(['research' => $research]);
    }

    public function store(Request $request, Campaign $campaign, OpponentProfile $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'in:policy_position,public_statement,scandal,voting_record,financial,media_coverage,legal,other'],
            'clearance_level' => ['sometimes', 'in:public,internal,confidential,restricted'],
            'date_published' => ['nullable', 'date'],
            'attached_media_url' => ['nullable', 'url', 'max:500'],
        ]);

        // Only users with view-confidential can create confidential/restricted research
        $clearance = $validated['clearance_level'] ?? 'internal';
        if (in_array($clearance, ['confidential', 'restricted']) && !$request->user()->can('opponents.view-confidential')) {
            return response()->json(['message' => 'You do not have permission to create research at this clearance level.'], 403);
        }

        $validated['opponent_profile_id'] = $opponent->id;
        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $research = OpponentResearch::create($validated);
        $research->load('creator:id,name');

        return response()->json([
            'message' => 'Research item created.',
            'research' => $research,
        ], 201);
    }

    public function show(Campaign $campaign, OpponentProfile $opponent, OpponentResearch $research): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $research->opponent_profile_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        // Enforce clearance on single item
        if (in_array($research->clearance_level, ['confidential', 'restricted'])) {
            if (!request()->user()->can('opponents.view-confidential')) {
                return response()->json(['message' => 'Insufficient clearance level.'], 403);
            }
        }

        $research->load('creator:id,name', 'opponentProfile:id,name');

        return response()->json(['research' => $research]);
    }

    public function update(Request $request, Campaign $campaign, OpponentProfile $opponent, OpponentResearch $research): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $research->opponent_profile_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'source_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'in:policy_position,public_statement,scandal,voting_record,financial,media_coverage,legal,other'],
            'clearance_level' => ['sometimes', 'in:public,internal,confidential,restricted'],
            'date_published' => ['sometimes', 'nullable', 'date'],
            'attached_media_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        // Prevent clearance escalation without permission
        if (isset($validated['clearance_level']) && in_array($validated['clearance_level'], ['confidential', 'restricted'])) {
            if (!$request->user()->can('opponents.view-confidential')) {
                return response()->json(['message' => 'You do not have permission to set this clearance level.'], 403);
            }
        }

        $research->update($validated);

        return response()->json([
            'message' => 'Research item updated.',
            'research' => $research->fresh()->load('creator:id,name'),
        ]);
    }

    public function destroy(Campaign $campaign, OpponentProfile $opponent, OpponentResearch $research): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $research->opponent_profile_id !== $opponent->id) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $research->delete();

        return response()->json(['message' => 'Research item deleted.']);
    }

    public function recent(Request $request, Campaign $campaign): JsonResponse
    {
        $query = $campaign->opponentResearch()
            ->with('opponentProfile:id,name', 'creator:id,name');

        // Enforce clearance
        if (!$request->user()->can('opponents.view-confidential')) {
            $query->whereIn('clearance_level', ['public', 'internal']);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $research = $query->orderByDesc('created_at')
            ->limit($request->integer('limit', 20))
            ->get();

        return response()->json(['research' => $research]);
    }
}
