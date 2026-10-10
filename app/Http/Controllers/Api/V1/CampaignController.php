<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Organisation;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $user = $request->user();

        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            $campaigns = Campaign::with('parent')
                ->withCount('activeMembers')
                ->paginate(20);
        } else {
            $campaignIds = $user->activeMemberships->pluck('campaign_id');
            $campaigns = Campaign::with('parent')
                ->withCount('activeMembers')
                ->whereIn('id', $campaignIds)
                ->paginate(20);
        }

        return response()->json($campaigns);
    }

    public function store(Request $request, LicenseService $license): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:campaigns'],
            'parent_id' => ['nullable', 'exists:campaigns,id'],
            'organisation_id' => ['nullable', 'exists:organisations,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'level' => ['required', 'in:national,county,constituency,ward'],
            'election_type' => ['required', 'in:presidential,gubernatorial,senatorial,woman_rep,parliamentary,mca,other'],
            'county' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'coalition' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']) . '-' . Str::random(6);

        $user = $request->user();
        $isPlatformOwner = $user->hasRole('platform-owner');

        // Which organisation does the new workspace belong to?
        // 1. an explicit organisation_id, 2. the parent workspace's organisation,
        // 3. otherwise a brand-new organisation (no modules until a platform admin grants them).
        $organisation = null;
        if (!empty($validated['organisation_id'])) {
            $organisation = Organisation::find($validated['organisation_id']);
            if (!$isPlatformOwner && !$this->leadsOrganisation($user, $organisation)) {
                return response()->json(['message' => 'You cannot create workspaces in that organisation.'], 403);
            }
        } elseif (!empty($validated['parent_id'])) {
            $organisation = Campaign::find($validated['parent_id'])?->organisation;
        }

        if ($organisation && !$isPlatformOwner && $license->workspaceLimitReached($organisation)) {
            return response()->json([
                'message' => 'This organisation has reached its workspace limit.',
                'code' => 'workspace_limit',
            ], 403);
        }

        $campaign = DB::transaction(function () use ($validated, $organisation, $isPlatformOwner, $user) {
            if (!$organisation && !$isPlatformOwner) {
                $organisation = Organisation::create([
                    'name' => $validated['name'],
                    'slug' => Str::slug($validated['name']) . '-' . Str::lower(Str::random(6)),
                    'contact_email' => $user->email,
                    'status' => 'active',
                ]);
            }

            $campaign = Campaign::create(array_merge($validated, [
                'organisation_id' => $organisation?->id,
            ]));

            // Add creator as campaign owner
            $campaign->members()->create([
                'user_id' => $user->id,
                'role' => 'campaign-owner',
                'visibility_scope' => $validated['level'] === 'national' ? 'national' : 'own_campaign',
            ]);

            return $campaign;
        });

        return response()->json([
            'message' => 'Campaign created.',
            'campaign' => $campaign->load('parent'),
        ], 201);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $campaign->load(['parent', 'children', 'site']);
        $campaign->loadCount(['activeMembers', 'children']);

        return response()->json(['campaign' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'level' => ['sometimes', 'in:national,county,constituency,ward'],
            'election_type' => ['sometimes', 'in:presidential,gubernatorial,senatorial,woman_rep,parliamentary,mca,other'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'constituency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:255'],
            'party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'coalition' => ['sometimes', 'nullable', 'string', 'max:255'],
            'election_phase' => ['sometimes', 'in:pre_campaign,campaign,e_day,post_election'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ]);

        $campaign->update($validated);

        return response()->json([
            'message' => 'Campaign updated.',
            'campaign' => $campaign->fresh(['parent', 'children']),
        ]);
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted.']);
    }

    public function hierarchy(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $campaign->load('descendants');

        return response()->json([
            'campaign' => $campaign,
            'ancestors' => $campaign->ancestors(),
        ]);
    }

    public function children(Campaign $campaign): JsonResponse
    {
        $this->authorize('viewChildren', $campaign);

        $children = $campaign->children()
            ->withCount('activeMembers')
            ->get();

        return response()->json(['children' => $children]);
    }

    /** Leadership role in some workspace of the organisation. */
    private function leadsOrganisation($user, Organisation $organisation): bool
    {
        return $user->activeMemberships()
            ->whereIn('role', ['campaign-owner', 'campaign-director', 'deputy-campaign-director'])
            ->whereIn('campaign_id', $organisation->campaigns()->pluck('id'))
            ->exists();
    }
}
