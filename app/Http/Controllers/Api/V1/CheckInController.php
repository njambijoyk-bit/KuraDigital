<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CheckIn;
use App\Models\FieldAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [CheckIn::class, $campaign]);

        $query = $campaign->checkIns()->with('user:id,name');

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $checkIns = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($checkIns);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [CheckIn::class, $campaign]);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:on_duty,break,off_duty,incident'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['user_id'] = $request->user()->id;

        $checkIn = CheckIn::create($validated);

        // Update agent's last_active_at
        FieldAgent::where('campaign_id', $campaign->id)
            ->where('user_id', $request->user()->id)
            ->update(['last_active_at' => now()]);

        return response()->json([
            'message' => 'Check-in recorded.',
            'check_in' => $checkIn,
        ], 201);
    }
}
