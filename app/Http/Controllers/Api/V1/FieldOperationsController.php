<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentCheckIn;
use App\Models\Campaign;
use App\Models\CanvassingAssignment;
use App\Models\VoterInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldOperationsController extends Controller
{
    // ──────────────────────────────────────────────
    // Canvassing Assignments
    // ──────────────────────────────────────────────

    public function assignments(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [CanvassingAssignment::class, $campaign]);

        $query = $campaign->canvassingAssignments()->with('assignee:id,name', 'assigner:id,name');

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $assignments = $query->withCount('interactions')
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 1 WHEN priority = 'high' THEN 2 WHEN priority = 'medium' THEN 3 WHEN priority = 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($assignments);
    }

    public function storeAssignment(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('manageAgents', [CanvassingAssignment::class, $campaign]);

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'due_date' => ['nullable', 'date'],
            'target_voters' => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['assigned_by'] = $request->user()->id;

        $assignment = CanvassingAssignment::create($validated);
        $assignment->load('assignee:id,name', 'assigner:id,name');

        return response()->json([
            'message' => 'Assignment created.',
            'assignment' => $assignment,
        ], 201);
    }

    public function showAssignment(Request $request, Campaign $campaign, CanvassingAssignment $assignment): JsonResponse
    {
        $this->authorize('viewAny', [CanvassingAssignment::class, $campaign]);

        if ($assignment->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($assignment)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $assignment->load('assignee:id,name', 'assigner:id,name');
        $assignment->loadCount('interactions');

        return response()->json(['assignment' => $assignment]);
    }

    public function updateAssignment(Request $request, Campaign $campaign, CanvassingAssignment $assignment): JsonResponse
    {
        $this->authorize('manageAgents', [CanvassingAssignment::class, $campaign]);

        if ($assignment->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($assignment)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:255'],
            'constituency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,in_progress,completed,cancelled'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'target_voters' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'completion_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'in_progress' && !$assignment->started_at) {
                $validated['started_at'] = now();
            }
            if ($validated['status'] === 'completed' && !$assignment->completed_at) {
                $validated['completed_at'] = now();
            }
        }

        $assignment->update($validated);

        return response()->json([
            'message' => 'Assignment updated.',
            'assignment' => $assignment->fresh()->load('assignee:id,name', 'assigner:id,name'),
        ]);
    }

    public function destroyAssignment(Request $request, Campaign $campaign, CanvassingAssignment $assignment): JsonResponse
    {
        $this->authorize('manageAgents', [CanvassingAssignment::class, $campaign]);

        if ($assignment->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted.']);
    }

    // ──────────────────────────────────────────────
    // Voter Interactions (door-to-door tracking)
    // ──────────────────────────────────────────────

    public function interactions(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [CanvassingAssignment::class, $campaign]);

        $query = $campaign->voterInteractions()->with('voter:id,name,phone,ward', 'agent:id,name');

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('outcome')) {
            $query->where('outcome', $request->input('outcome'));
        }

        if ($request->has('interaction_type')) {
            $query->where('interaction_type', $request->input('interaction_type'));
        }

        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }

        if ($request->has('assignment_id')) {
            $query->where('assignment_id', $request->input('assignment_id'));
        }

        if ($request->has('voter_id')) {
            $query->where('voter_id', $request->input('voter_id'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $interactions = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($interactions);
    }

    public function storeInteraction(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('submitCheckIn', [CanvassingAssignment::class, $campaign]);

        $validated = $request->validate([
            'voter_id' => ['required', 'integer', 'exists:voters,id'],
            'assignment_id' => ['nullable', 'integer', 'exists:canvassing_assignments,id'],
            'interaction_type' => ['required', 'in:door_knock,phone_call,rally,community_meeting,market_visit,church_visit,other'],
            'outcome' => ['required', 'in:contacted,not_home,refused,supportive,undecided,hostile,moved,other'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        // Verify voter belongs to this campaign
        $voter = $campaign->voters()->find($validated['voter_id']);
        if (!$voter) {
            return response()->json(['message' => 'Voter not found in this campaign.'], 404);
        }

        // Verify assignment belongs to this campaign if provided
        if (isset($validated['assignment_id'])) {
            $assignment = $campaign->canvassingAssignments()->find($validated['assignment_id']);
            if (!$assignment) {
                return response()->json(['message' => 'Assignment not found in this campaign.'], 404);
            }
        }

        $validated['campaign_id'] = $campaign->id;
        $validated['agent_id'] = $request->user()->id;

        // Auto-fill geographic data from voter if not provided
        if (empty($validated['ward']) && $voter->ward) {
            $validated['ward'] = $voter->ward;
        }
        if (empty($validated['constituency']) && $voter->constituency) {
            $validated['constituency'] = $voter->constituency;
        }
        if (empty($validated['county']) && $voter->county) {
            $validated['county'] = $voter->county;
        }

        $interaction = VoterInteraction::create($validated);

        // Update voter's last_contacted_at
        $voter->update(['last_contacted_at' => now()]);

        $interaction->load('voter:id,name,phone,ward', 'agent:id,name');

        return response()->json([
            'message' => 'Interaction recorded.',
            'interaction' => $interaction,
        ], 201);
    }

    public function showInteraction(Request $request, Campaign $campaign, VoterInteraction $interaction): JsonResponse
    {
        $this->authorize('viewAny', [CanvassingAssignment::class, $campaign]);

        if ($interaction->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Interaction not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($interaction)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $interaction->load('voter:id,name,phone,ward', 'agent:id,name', 'assignment:id,title');

        return response()->json(['interaction' => $interaction]);
    }

    // ──────────────────────────────────────────────
    // Agent Check-ins
    // ──────────────────────────────────────────────

    public function checkIns(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAgentLocations', [CanvassingAssignment::class, $campaign]);

        $query = $campaign->agentCheckIns()->with('user:id,name');

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('check_in_type')) {
            $query->where('check_in_type', $request->input('check_in_type'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $checkIns = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($checkIns);
    }

    public function storeCheckIn(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('submitCheckIn', [CanvassingAssignment::class, $campaign]);

        $validated = $request->validate([
            'assignment_id' => ['nullable', 'integer', 'exists:canvassing_assignments,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'check_in_type' => ['nullable', 'in:start_shift,end_shift,break,location_update,incident'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['assignment_id'])) {
            $assignment = $campaign->canvassingAssignments()->find($validated['assignment_id']);
            if (!$assignment) {
                return response()->json(['message' => 'Assignment not found in this campaign.'], 404);
            }
        }

        $validated['campaign_id'] = $campaign->id;
        $validated['user_id'] = $request->user()->id;

        $checkIn = AgentCheckIn::create($validated);
        $checkIn->load('user:id,name');

        return response()->json([
            'message' => 'Check-in recorded.',
            'check_in' => $checkIn,
        ], 201);
    }

    // ──────────────────────────────────────────────
    // Field Operations Stats / Dashboard
    // ──────────────────────────────────────────────

    public function stats(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [CanvassingAssignment::class, $campaign]);

        $membership = $request->user()->membershipFor($campaign);

        // Assignment stats
        $assignmentQuery = $campaign->canvassingAssignments();
        if ($membership) {
            $membership->applyGeographicFilters($assignmentQuery, ['ward', 'constituency', 'county']);
        }

        $assignmentsByStatus = (clone $assignmentQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalAssignments = (clone $assignmentQuery)->count();

        // Interaction stats
        $interactionQuery = $campaign->voterInteractions();
        if ($membership) {
            $membership->applyGeographicFilters($interactionQuery, ['ward', 'constituency', 'county']);
        }

        $totalInteractions = (clone $interactionQuery)->count();

        $interactionsByOutcome = (clone $interactionQuery)
            ->select('outcome', DB::raw('count(*) as count'))
            ->groupBy('outcome')
            ->pluck('count', 'outcome');

        $interactionsToday = (clone $interactionQuery)
            ->whereDate('created_at', today())
            ->count();

        $interactionsThisWeek = (clone $interactionQuery)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Check-in stats
        $checkInQuery = $campaign->agentCheckIns();
        if ($membership) {
            $membership->applyGeographicFilters($checkInQuery, ['ward', 'constituency', 'county']);
        }

        $totalCheckIns = (clone $checkInQuery)->count();

        $activeAgentsToday = (clone $checkInQuery)
            ->whereDate('created_at', today())
            ->distinct('user_id')
            ->count('user_id');

        // Top agents by interactions this week
        $topAgents = (clone $interactionQuery)
            ->where('created_at', '>=', now()->subDays(7))
            ->select('agent_id', DB::raw('count(*) as count'))
            ->groupBy('agent_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $user = \App\Models\User::find($row->agent_id);
                return [
                    'agent_id' => $row->agent_id,
                    'name' => $user?->name,
                    'interactions' => $row->count,
                ];
            });

        return response()->json([
            'assignments' => [
                'total' => $totalAssignments,
                'by_status' => $assignmentsByStatus,
            ],
            'interactions' => [
                'total' => $totalInteractions,
                'today' => $interactionsToday,
                'this_week' => $interactionsThisWeek,
                'by_outcome' => $interactionsByOutcome,
            ],
            'check_ins' => [
                'total' => $totalCheckIns,
                'active_agents_today' => $activeAgentsToday,
            ],
            'top_agents' => $topAgents,
        ]);
    }
}
