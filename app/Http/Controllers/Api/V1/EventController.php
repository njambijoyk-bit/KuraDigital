<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Event::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $query = $site->allEvents();

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($request->has('from_date')) {
            $query->where('date', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('date', '<=', $request->input('to_date'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $events = $query->paginate(20);

        return response()->json($events);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Event::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:500'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['site_id'] = $site->id;
        $validated['is_published'] = $validated['is_published'] ?? false;

        $event = Event::create($validated);

        return response()->json([
            'message' => 'Event created.',
            'event' => $event,
        ], 201);
    }

    public function show(Campaign $campaign, Event $event): JsonResponse
    {
        $this->authorize('viewAny', [Event::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $event)) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        return response()->json(['event' => $event]);
    }

    public function update(Request $request, Campaign $campaign, Event $event): JsonResponse
    {
        $this->authorize('update', [Event::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $event)) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'nullable', 'string', 'max:50'],
            'location' => ['sometimes', 'nullable', 'string', 'max:500'],
            'map_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Event updated.',
            'event' => $event->fresh(),
        ]);
    }

    public function destroy(Campaign $campaign, Event $event): JsonResponse
    {
        $this->authorize('delete', [Event::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $event)) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted.']);
    }

    private function belongsToCampaign(Campaign $campaign, Event $event): bool
    {
        return $campaign->site && $event->site_id === $campaign->site->id;
    }
}
