<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteSettingsController extends Controller
{
    public function show(Campaign $campaign): JsonResponse
    {
        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        return response()->json(['site' => $site]);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->site) {
            return response()->json(['message' => 'Campaign already has a site.'], 409);
        }

        $validated = $request->validate([
            'candidate_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:sites,slug'],
            'position' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'slogan_sw' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:50'],
            'secondary_color' => ['nullable', 'string', 'max:50'],
            'bio_summary' => ['nullable', 'string'],
            'bio_summary_sw' => ['nullable', 'string'],
            'bio_full' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'experience' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'twitter_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],
            'tiktok_url' => ['nullable', 'url', 'max:500'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'donation_info' => ['nullable', 'string'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['candidate_name']) . '-' . Str::random(6);

        $site = Site::create($validated);

        $campaign->update(['site_id' => $site->id]);

        return response()->json([
            'message' => 'Site created.',
            'site' => $site,
        ], 201);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $validated = $request->validate([
            'candidate_name' => ['sometimes', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'constituency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slogan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slogan_sw' => ['sometimes', 'nullable', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'portrait_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'hero_image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'about_image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'bio_summary' => ['sometimes', 'nullable', 'string'],
            'bio_summary_sw' => ['sometimes', 'nullable', 'string'],
            'bio_full' => ['sometimes', 'nullable', 'string'],
            'education' => ['sometimes', 'nullable', 'string'],
            'experience' => ['sometimes', 'nullable', 'string'],
            'pillars' => ['sometimes', 'nullable', 'array'],
            'milestones' => ['sometimes', 'nullable', 'array'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'office_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'facebook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'twitter_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'instagram_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'tiktok_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'youtube_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'donation_info' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $site->update($validated);

        return response()->json([
            'message' => 'Site updated.',
            'site' => $site->fresh(),
        ]);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $site->update(['is_active' => false]);

        return response()->json(['message' => 'Site deactivated.']);
    }
}
