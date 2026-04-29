<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\OpponentProfile;
use App\Models\OpponentSwotEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentSwotController extends Controller
{
    public function index(Request $request, Campaign $campaign, OpponentProfile $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $query = $opponent->swotEntries()->with('creator:id,name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('impact_level')) {
            $query->where('impact_level', $request->impact_level);
        }

        $entries = $query->orderBy('type')
            ->orderByDesc('impact_level')
            ->get();

        // Group by type for structured response
        $grouped = [
            'strengths' => $entries->where('type', 'strength')->values(),
            'weaknesses' => $entries->where('type', 'weakness')->values(),
            'opportunities' => $entries->where('type', 'opportunity')->values(),
            'threats' => $entries->where('type', 'threat')->values(),
        ];

        return response()->json([
            'swot' => $grouped,
            'total' => $entries->count(),
        ]);
    }

    public function store(Request $request, Campaign $campaign, OpponentProfile $opponent): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Opponent not found.'], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:strength,weakness,opportunity,threat'],
            'description' => ['required', 'string'],
            'impact_level' => ['sometimes', 'in:low,medium,high'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['opponent_profile_id'] = $opponent->id;
        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $entry = OpponentSwotEntry::create($validated);
        $entry->load('creator:id,name');

        return response()->json([
            'message' => 'SWOT entry created.',
            'entry' => $entry,
        ], 201);
    }

    public function show(Campaign $campaign, OpponentProfile $opponent, OpponentSwotEntry $entry): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $entry->opponent_profile_id !== $opponent->id) {
            return response()->json(['message' => 'SWOT entry not found.'], 404);
        }

        $entry->load('creator:id,name', 'opponentProfile:id,name');

        return response()->json(['entry' => $entry]);
    }

    public function update(Request $request, Campaign $campaign, OpponentProfile $opponent, OpponentSwotEntry $entry): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $entry->opponent_profile_id !== $opponent->id) {
            return response()->json(['message' => 'SWOT entry not found.'], 404);
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'in:strength,weakness,opportunity,threat'],
            'description' => ['sometimes', 'string'],
            'impact_level' => ['sometimes', 'in:low,medium,high'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $entry->update($validated);

        return response()->json([
            'message' => 'SWOT entry updated.',
            'entry' => $entry->fresh()->load('creator:id,name'),
        ]);
    }

    public function destroy(Campaign $campaign, OpponentProfile $opponent, OpponentSwotEntry $entry): JsonResponse
    {
        if ($opponent->campaign_id !== $campaign->id || $entry->opponent_profile_id !== $opponent->id) {
            return response()->json(['message' => 'SWOT entry not found.'], 404);
        }

        $entry->delete();

        return response()->json(['message' => 'SWOT entry deleted.']);
    }
}
