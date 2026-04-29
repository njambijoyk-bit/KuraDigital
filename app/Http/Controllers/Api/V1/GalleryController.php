<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\GalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $query = $site->allGalleryItems();

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $items = $query->paginate(20);

        return response()->json($items);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:image,video'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['site_id'] = $site->id;
        $validated['type'] = $validated['type'] ?? 'image';

        $item = GalleryItem::create($validated);

        return response()->json([
            'message' => 'Gallery item created.',
            'item' => $item,
        ], 201);
    }

    public function show(Campaign $campaign, GalleryItem $item): JsonResponse
    {
        if (!$this->belongsToCampaign($campaign, $item)) {
            return response()->json(['message' => 'Gallery item not found.'], 404);
        }

        return response()->json(['item' => $item]);
    }

    public function update(Request $request, Campaign $campaign, GalleryItem $item): JsonResponse
    {
        if (!$this->belongsToCampaign($campaign, $item)) {
            return response()->json(['message' => 'Gallery item not found.'], 404);
        }

        $validated = $request->validate([
            'url' => ['sometimes', 'string', 'max:500'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type' => ['sometimes', 'in:image,video'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Gallery item updated.',
            'item' => $item->fresh(),
        ]);
    }

    public function destroy(Campaign $campaign, GalleryItem $item): JsonResponse
    {
        if (!$this->belongsToCampaign($campaign, $item)) {
            return response()->json(['message' => 'Gallery item not found.'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Gallery item deleted.']);
    }

    private function belongsToCampaign(Campaign $campaign, GalleryItem $item): bool
    {
        return $campaign->site && $item->site_id === $campaign->site->id;
    }
}
