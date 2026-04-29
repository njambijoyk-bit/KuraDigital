<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function members(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [CampaignMember::class, $campaign]);

        $members = $campaign->members()
            ->with(['user' => fn ($q) => $q->select('id', 'name', 'email', 'phone', 'account_status')])
            ->with(['user.roles'])
            ->paginate(20);

        return response()->json($members);
    }

    public function invite(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('invite', [CampaignMember::class, $campaign]);

        $validated = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'assigned_wards' => ['nullable', 'array'],
            'assigned_wards.*' => ['string'],
            'assigned_constituencies' => ['nullable', 'array'],
            'assigned_constituencies.*' => ['string'],
            'assigned_counties' => ['nullable', 'array'],
            'assigned_counties.*' => ['string'],
        ]);

        $invitation = TeamInvitation::create([
            'campaign_id' => $campaign->id,
            'invited_by' => $request->user()->id,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'token' => Str::random(64),
            'role' => $validated['role'],
            'assigned_wards' => $validated['assigned_wards'] ?? null,
            'assigned_constituencies' => $validated['assigned_constituencies'] ?? null,
            'assigned_counties' => $validated['assigned_counties'] ?? null,
            'expires_at' => now()->addDays(7),
        ]);

        // TODO: Send invitation via email/SMS using Africa's Talking

        return response()->json([
            'message' => 'Invitation sent.',
            'invitation' => $invitation,
        ], 201);
    }

    public function acceptInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $invitation = TeamInvitation::where('token', $validated['token'])
            ->where('status', 'pending')
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invalid invitation.'], 404);
        }

        if ($invitation->isExpired()) {
            $invitation->update(['status' => 'expired']);
            return response()->json(['message' => 'Invitation expired.'], 400);
        }

        $user = $request->user();

        // Create campaign membership
        CampaignMember::updateOrCreate(
            ['user_id' => $user->id, 'campaign_id' => $invitation->campaign_id],
            [
                'is_active' => true,
                'assigned_wards' => $invitation->assigned_wards,
                'assigned_constituencies' => $invitation->assigned_constituencies,
                'assigned_counties' => $invitation->assigned_counties,
                'joined_at' => now(),
                'deactivated_at' => null,
            ]
        );

        // Assign the role
        $user->assignRole($invitation->role);

        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Invitation accepted.',
            'campaign' => $invitation->campaign,
        ]);
    }

    public function updateMember(Request $request, Campaign $campaign, CampaignMember $member): JsonResponse
    {
        $this->authorize('update', $member);

        if ($member->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Member not in this campaign.'], 404);
        }

        $validated = $request->validate([
            'role' => ['sometimes', 'string', 'exists:roles,name'],
            'visibility_scope' => ['sometimes', 'in:own_campaign,county,national'],
            'assigned_wards' => ['sometimes', 'nullable', 'array'],
            'assigned_wards.*' => ['string'],
            'assigned_constituencies' => ['sometimes', 'nullable', 'array'],
            'assigned_constituencies.*' => ['string'],
            'assigned_counties' => ['sometimes', 'nullable', 'array'],
            'assigned_counties.*' => ['string'],
            'assigned_polling_stations' => ['sometimes', 'nullable', 'array'],
            'assigned_polling_stations.*' => ['string'],
        ]);

        if (isset($validated['role'])) {
            $member->user->syncRoles([$validated['role']]);
            unset($validated['role']);
        }

        $member->update($validated);

        return response()->json([
            'message' => 'Member updated.',
            'member' => $member->fresh(['user', 'user.roles']),
        ]);
    }

    public function deactivateMember(Request $request, Campaign $campaign, CampaignMember $member): JsonResponse
    {
        $this->authorize('deactivate', $member);

        if ($member->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Member not in this campaign.'], 404);
        }

        if ($member->user_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate yourself.'], 400);
        }

        $member->deactivate();

        return response()->json(['message' => 'Member deactivated.']);
    }

    public function pendingInvitations(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [CampaignMember::class, $campaign]);

        $invitations = $campaign->invitations()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('inviter:id,name')
            ->get();

        return response()->json(['invitations' => $invitations]);
    }

    public function revokeInvitation(Request $request, Campaign $campaign, TeamInvitation $invitation): JsonResponse
    {
        $this->authorize('invite', [CampaignMember::class, $campaign]);

        if ($invitation->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Invitation not in this campaign.'], 404);
        }

        $invitation->update(['status' => 'revoked']);

        return response()->json(['message' => 'Invitation revoked.']);
    }
}
