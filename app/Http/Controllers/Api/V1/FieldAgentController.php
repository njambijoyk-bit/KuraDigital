<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\FieldAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldAgentController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [FieldAgent::class, $campaign]);

        $query = $campaign->fieldAgents()->with('user:id,name,email,phone');

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('agent_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $agents = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($agents);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [FieldAgent::class, $campaign]);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'agent_code' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'polling_station' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['campaign_id'] = $campaign->id;

        $existing = FieldAgent::where('campaign_id', $campaign->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'User is already a field agent in this campaign.'], 422);
        }

        $agent = FieldAgent::create($validated);
        $agent->load('user:id,name,email,phone');

        return response()->json([
            'message' => 'Field agent added.',
            'field_agent' => $agent,
        ], 201);
    }

    public function show(Request $request, Campaign $campaign, FieldAgent $fieldAgent): JsonResponse
    {
        $this->authorize('view', [FieldAgent::class, $campaign]);

        if ($fieldAgent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Field agent not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($fieldAgent)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $fieldAgent->load('user:id,name,email,phone');
        $fieldAgent->loadCount('checkIns');

        return response()->json(['field_agent' => $fieldAgent]);
    }

    public function update(Request $request, Campaign $campaign, FieldAgent $fieldAgent): JsonResponse
    {
        $this->authorize('update', [FieldAgent::class, $campaign]);

        if ($fieldAgent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Field agent not found.'], 404);
        }

        $validated = $request->validate([
            'agent_code' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'polling_station' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $fieldAgent->update($validated);
        $fieldAgent->load('user:id,name,email,phone');

        return response()->json([
            'message' => 'Field agent updated.',
            'field_agent' => $fieldAgent,
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, FieldAgent $fieldAgent): JsonResponse
    {
        $this->authorize('delete', [FieldAgent::class, $campaign]);

        if ($fieldAgent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Field agent not found.'], 404);
        }

        $fieldAgent->delete();

        return response()->json(['message' => 'Field agent removed.']);
    }

    public function locations(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewLocations', [FieldAgent::class, $campaign]);

        $query = $campaign->checkIns()
            ->with('user:id,name')
            ->select('check_ins.*')
            ->whereIn('user_id', $campaign->fieldAgents()->pluck('user_id'));

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        // Latest check-in per agent
        $locations = $query->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->unique('user_id')
            ->values();

        return response()->json(['locations' => $locations]);
    }

    public function assignStation(Request $request, Campaign $campaign, FieldAgent $fieldAgent): JsonResponse
    {
        $this->authorize('assignStations', [FieldAgent::class, $campaign]);

        if ($fieldAgent->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Field agent not found.'], 404);
        }

        $validated = $request->validate([
            'polling_station' => ['required', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
        ]);

        $fieldAgent->update($validated);
        $fieldAgent->load('user:id,name,email,phone');

        return response()->json([
            'message' => 'Station assigned.',
            'field_agent' => $fieldAgent,
        ]);
    }
}
