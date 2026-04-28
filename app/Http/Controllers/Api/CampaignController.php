<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = $request->user()->campaigns()
            ->withPivot('role', 'status')
            ->withCount('campaignMembers')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $campaigns]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'candidate_name' => 'required|string|max:255',
            'position' => 'sometimes|string|max:255',
            'constituency' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
            'party' => 'nullable|string|max:255',
            'election_year' => 'sometimes|string|max:10',
            'slogan' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
        ]);

        $campaign = Campaign::create([
            ...$validated,
            'owner_id' => $request->user()->id,
        ]);

        CampaignMember::create([
            'campaign_id' => $campaign->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return response()->json(['data' => $campaign->load('owner')], 201);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeMember($request, $campaign);

        $campaign->load(['owner', 'members' => function ($q) {
            $q->withPivot('role', 'status', 'joined_at');
        }, 'site']);

        return response()->json(['data' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeRole($request, $campaign, ['owner', 'manager']);

        $validated = $request->validate([
            'candidate_name' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'constituency' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
            'party' => 'nullable|string|max:255',
            'election_year' => 'sometimes|string|max:10',
            'slogan' => 'nullable|string|max:500',
            'primary_color' => 'sometimes|string|max:20',
            'secondary_color' => 'sometimes|string|max:20',
            'description' => 'nullable|string|max:2000',
            'status' => 'sometimes|in:draft,active,paused,completed',
        ]);

        $campaign->update($validated);

        return response()->json(['data' => $campaign->fresh()]);
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeRole($request, $campaign, ['owner']);

        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted']);
    }

    public function members(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeMember($request, $campaign);

        $members = $campaign->members()
            ->withPivot('role', 'status', 'joined_at')
            ->get();

        return response()->json(['data' => $members]);
    }

    public function inviteMember(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeRole($request, $campaign, ['owner', 'manager']);

        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'role' => 'required|in:manager,researcher,field_agent,analyst,coordinator',
        ]);

        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'password' => Hash::make(str()->random(16)),
            ]
        );

        $existing = CampaignMember::where('campaign_id', $campaign->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'User is already a member of this campaign'], 422);
        }

        CampaignMember::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'role' => $validated['role'],
            'status' => 'invited',
        ]);

        return response()->json(['message' => 'Invitation sent', 'user' => $user], 201);
    }

    public function updateMember(Request $request, Campaign $campaign, CampaignMember $member): JsonResponse
    {
        $this->authorizeRole($request, $campaign, ['owner', 'manager']);

        if ($member->campaign_id !== $campaign->id) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => 'sometimes|in:manager,researcher,field_agent,analyst,coordinator',
            'status' => 'sometimes|in:invited,active,suspended',
        ]);

        $member->update($validated);

        return response()->json(['data' => $member->fresh()->load('user')]);
    }

    public function removeMember(Request $request, Campaign $campaign, CampaignMember $member): JsonResponse
    {
        $this->authorizeRole($request, $campaign, ['owner', 'manager']);

        if ($member->campaign_id !== $campaign->id) {
            abort(404);
        }

        if ($member->role === 'owner') {
            return response()->json(['message' => 'Cannot remove the campaign owner'], 422);
        }

        $member->delete();

        return response()->json(['message' => 'Member removed']);
    }

    private function authorizeMember(Request $request, Campaign $campaign): void
    {
        $isMember = CampaignMember::where('campaign_id', $campaign->id)
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'suspended')
            ->exists();

        if (!$isMember) {
            abort(403, 'You are not a member of this campaign');
        }
    }

    private function authorizeRole(Request $request, Campaign $campaign, array $roles): void
    {
        $member = CampaignMember::where('campaign_id', $campaign->id)
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'suspended')
            ->first();

        if (!$member || !in_array($member->role, $roles)) {
            abort(403, 'Insufficient permissions');
        }
    }
}
