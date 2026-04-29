<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ManifestoPillar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManifestoController extends Controller
{
    public function index(Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [ManifestoPillar::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $pillars = $site->allManifestoPillars()->get();

        return response()->json(['pillars' => $pillars]);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [ManifestoPillar::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $validated = $request->validate([
            'icon' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'title_sw' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_sw' => ['nullable', 'string'],
            'promises' => ['nullable', 'array'],
            'promises.*' => ['string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['site_id'] = $site->id;

        if (empty($validated['icon'] ?? null)) {
            $validated['icon'] = '📋';
        }

        $pillar = ManifestoPillar::create($validated);

        return response()->json([
            'message' => 'Manifesto pillar created.',
            'pillar' => $pillar,
        ], 201);
    }

    public function show(Campaign $campaign, ManifestoPillar $pillar): JsonResponse
    {
        $this->authorize('viewAny', [ManifestoPillar::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $pillar)) {
            return response()->json(['message' => 'Pillar not found.'], 404);
        }

        return response()->json(['pillar' => $pillar]);
    }

    public function update(Request $request, Campaign $campaign, ManifestoPillar $pillar): JsonResponse
    {
        $this->authorize('update', [ManifestoPillar::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $pillar)) {
            return response()->json(['message' => 'Pillar not found.'], 404);
        }

        $validated = $request->validate([
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'title_sw' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'description_sw' => ['sometimes', 'nullable', 'string'],
            'promises' => ['sometimes', 'nullable', 'array'],
            'promises.*' => ['string'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $pillar->update($validated);

        return response()->json([
            'message' => 'Manifesto pillar updated.',
            'pillar' => $pillar->fresh(),
        ]);
    }

    public function destroy(Campaign $campaign, ManifestoPillar $pillar): JsonResponse
    {
        $this->authorize('delete', [ManifestoPillar::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $pillar)) {
            return response()->json(['message' => 'Pillar not found.'], 404);
        }

        $pillar->delete();

        return response()->json(['message' => 'Manifesto pillar deleted.']);
    }

    private function belongsToCampaign(Campaign $campaign, ManifestoPillar $pillar): bool
    {
        return $campaign->site && $pillar->site_id === $campaign->site->id;
    }
}
