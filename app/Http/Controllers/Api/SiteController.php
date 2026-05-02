<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Voter;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function show(string $slug)
    {
        $site = Site::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json(['data' => $site]);
    }

    public function manifesto(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->manifestoPillars,
        ]);
    }

    public function events(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->events()->where('date', '>=', now())->get(),
        ]);
    }

    public function news(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->newsArticles,
        ]);
    }

    public function gallery(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->galleryItems,
        ]);
    }

    public function projects(string $siteId)
    {
        $site = Site::findOrFail($siteId);

        return response()->json([
            'data' => $site->projects,
        ]);
    }

    public function storeContact(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:5000',
        ]);

        $site->contactMessages()->create($validated);

        return response()->json(['message' => 'Message sent successfully'], 201);
    }

    public function storeVolunteer(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'ward' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:100',
            'skills' => 'nullable|string|max:2000',
        ]);

        $site->volunteers()->create($validated);

        return response()->json(['message' => 'Thank you for volunteering!'], 201);
    }

    public function registerSupporter(Request $request, string $siteId)
    {
        $site = Site::findOrFail($siteId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'national_id' => 'nullable|string|max:50',
            'county' => 'nullable|string|max:255',
            'constituency' => 'nullable|string|max:255',
            'ward' => 'nullable|string|max:255',
            'polling_station' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:50',
        ]);

        $campaign = $site->campaign;
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not configured for this site.'], 422);
        }

        $validated['campaign_id'] = $campaign->id;
        $validated['supporter_status'] = 'supporter';
        $validated['source'] = 'online';

        Voter::create($validated);

        return response()->json(['message' => 'Thank you for registering your support!'], 201);
    }
}
