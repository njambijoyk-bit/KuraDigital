<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Poll;
use App\Models\StrategyNote;
use App\Models\WardTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StrategyController extends Controller
{
    // =====================================================================
    // Strategy Notes
    // =====================================================================

    public function notesIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [StrategyNote::class, $campaign]);

        $query = $campaign->strategyNotes()->with('creator:id,name');

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        // ABAC clearance filtering
        $clearanceLevels = $this->allowedClearanceLevels($request->user()->clearance_level ?? 'public');
        $query->whereIn('clearance_level', $clearanceLevels);

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $notes = $query->orderByDesc('updated_at')->paginate(20);

        return response()->json($notes);
    }

    public function notesStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [StrategyNote::class, $campaign]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'category' => ['nullable', 'in:general,swot,talking-point,risk,opportunity'],
            'clearance_level' => ['nullable', 'in:public,internal,confidential,top_secret'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $note = StrategyNote::create($validated);
        $note->load('creator:id,name');

        return response()->json([
            'message' => 'Strategy note created.',
            'strategy_note' => $note,
        ], 201);
    }

    public function notesShow(Request $request, Campaign $campaign, StrategyNote $strategyNote): JsonResponse
    {
        $this->authorize('view', [StrategyNote::class, $campaign]);

        if ($strategyNote->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Strategy note not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($strategyNote)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $clearanceLevels = $this->allowedClearanceLevels($request->user()->clearance_level ?? 'public');
        if (!in_array($strategyNote->clearance_level, $clearanceLevels)) {
            return response()->json(['message' => 'Insufficient clearance level.'], 403);
        }

        $strategyNote->load('creator:id,name');

        return response()->json(['strategy_note' => $strategyNote]);
    }

    public function notesUpdate(Request $request, Campaign $campaign, StrategyNote $strategyNote): JsonResponse
    {
        $this->authorize('update', [StrategyNote::class, $campaign]);

        if ($strategyNote->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Strategy note not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'category' => ['nullable', 'in:general,swot,talking-point,risk,opportunity'],
            'clearance_level' => ['nullable', 'in:public,internal,confidential,top_secret'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
        ]);

        $strategyNote->update($validated);

        return response()->json([
            'message' => 'Strategy note updated.',
            'strategy_note' => $strategyNote,
        ]);
    }

    public function notesDestroy(Request $request, Campaign $campaign, StrategyNote $strategyNote): JsonResponse
    {
        $this->authorize('delete', [StrategyNote::class, $campaign]);

        if ($strategyNote->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Strategy note not found.'], 404);
        }

        $strategyNote->delete();

        return response()->json(['message' => 'Strategy note deleted.']);
    }

    // =====================================================================
    // Ward Targets
    // =====================================================================

    public function wardTargetsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [WardTarget::class, $campaign]);

        $query = $campaign->wardTargets();

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('search')) {
            $query->where('ward', 'like', "%{$request->input('search')}%");
        }

        $targets = $query->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->paginate(50);

        return response()->json($targets);
    }

    public function wardTargetsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [WardTarget::class, $campaign]);

        $validated = $request->validate([
            'ward' => ['required', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'registered_voters' => ['nullable', 'integer', 'min:0'],
            'target_votes' => ['nullable', 'integer', 'min:0'],
            'projected_turnout' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'in:critical,high,medium,low'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $existing = WardTarget::where('campaign_id', $campaign->id)
            ->where('ward', $validated['ward'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A target for this ward already exists.'], 422);
        }

        $target = WardTarget::create($validated);

        return response()->json([
            'message' => 'Ward target created.',
            'ward_target' => $target,
        ], 201);
    }

    public function wardTargetsUpdate(Request $request, Campaign $campaign, WardTarget $wardTarget): JsonResponse
    {
        $this->authorize('update', [WardTarget::class, $campaign]);

        if ($wardTarget->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Ward target not found.'], 404);
        }

        $validated = $request->validate([
            'registered_voters' => ['nullable', 'integer', 'min:0'],
            'target_votes' => ['nullable', 'integer', 'min:0'],
            'projected_turnout' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'in:critical,high,medium,low'],
            'notes' => ['nullable', 'string'],
        ]);

        $wardTarget->update($validated);

        return response()->json([
            'message' => 'Ward target updated.',
            'ward_target' => $wardTarget,
        ]);
    }

    public function wardTargetsDestroy(Request $request, Campaign $campaign, WardTarget $wardTarget): JsonResponse
    {
        $this->authorize('delete', [WardTarget::class, $campaign]);

        if ($wardTarget->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Ward target not found.'], 404);
        }

        $wardTarget->delete();

        return response()->json(['message' => 'Ward target deleted.']);
    }

    // =====================================================================
    // Polls
    // =====================================================================

    public function pollsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Poll::class, $campaign]);

        $query = $campaign->polls()->with('creator:id,name');

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        // ABAC clearance filtering
        $clearanceLevels = $this->allowedClearanceLevels($request->user()->clearance_level ?? 'public');
        $query->whereIn('clearance_level', $clearanceLevels);

        $polls = $query->orderByDesc('poll_date')->paginate(20);

        return response()->json($polls);
    }

    public function pollsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Poll::class, $campaign]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pollster' => ['nullable', 'string', 'max:255'],
            'poll_date' => ['required', 'date'],
            'sample_size' => ['nullable', 'integer', 'min:1'],
            'margin_of_error' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.candidate' => ['required', 'string'],
            'results.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'results.*.party' => ['nullable', 'string'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'clearance_level' => ['nullable', 'in:public,internal,confidential,top_secret'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $poll = Poll::create($validated);
        $poll->load('creator:id,name');

        return response()->json([
            'message' => 'Poll created.',
            'poll' => $poll,
        ], 201);
    }

    public function pollsShow(Request $request, Campaign $campaign, Poll $poll): JsonResponse
    {
        $this->authorize('view', [Poll::class, $campaign]);

        if ($poll->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $clearanceLevels = $this->allowedClearanceLevels($request->user()->clearance_level ?? 'public');
        if (!in_array($poll->clearance_level, $clearanceLevels)) {
            return response()->json(['message' => 'Insufficient clearance level.'], 403);
        }

        $poll->load('creator:id,name');

        return response()->json(['poll' => $poll]);
    }

    public function pollsDestroy(Request $request, Campaign $campaign, Poll $poll): JsonResponse
    {
        $this->authorize('delete', [Poll::class, $campaign]);

        if ($poll->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'Poll deleted.']);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function allowedClearanceLevels(string $userLevel): array
    {
        $hierarchy = ['public', 'internal', 'confidential', 'top_secret'];
        $index = array_search($userLevel, $hierarchy);
        if ($index === false) {
            return ['public'];
        }
        return array_slice($hierarchy, 0, $index + 1);
    }
}
