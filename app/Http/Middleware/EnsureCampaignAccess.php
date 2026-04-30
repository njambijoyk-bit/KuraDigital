<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampaignAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $campaignId = $request->route('campaign')
            ?? $request->route('campaignId')
            ?? $request->header('X-Campaign-Id');

        if (!$campaignId) {
            return response()->json(['message' => 'Campaign context required.'], 400);
        }

        $campaign = $campaignId instanceof Campaign
            ? $campaignId
            : Campaign::find($campaignId);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found.'], 404);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Platform owners/support bypass campaign membership checks
        if ($user->hasRole(['platform-owner', 'platform-support'])) {
            $request->merge(['current_campaign' => $campaign]);
            return $next($request);
        }

        // M1: Check account status before membership to avoid info leak
        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Your account has been ' . $user->account_status . '.',
            ], 403);
        }

        // Check direct membership
        $membership = $user->membershipFor($campaign);

        // Check parent campaign membership with visibility scope
        if (!$membership) {
            $membership = $this->checkHierarchicalAccess($user, $campaign);
        }

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this campaign.'], 403);
        }

        $request->merge([
            'current_campaign' => $campaign,
            'current_membership' => $membership,
        ]);

        return $next($request);
    }

    private function checkHierarchicalAccess($user, Campaign $campaign)
    {
        $current = $campaign->parent;

        while ($current) {
            $membership = $user->membershipFor($current);

            if ($membership) {
                $scope = $membership->visibility_scope;

                if ($scope === 'national') {
                    return $membership;
                }

                if ($scope === 'county' && $campaign->county === $current->county) {
                    return $membership;
                }

                if ($scope === 'constituency' && $campaign->constituency === $current->constituency) {
                    return $membership;
                }
            }

            $current = $current->parent;
        }

        return null;
    }
}
