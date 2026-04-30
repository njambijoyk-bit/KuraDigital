<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Project::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $query = $site->allProjects();

        // Apply geographic ABAC filters
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $projects = $query->paginate(20);

        return response()->json($projects);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Project::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:planned,ongoing,completed'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'impact' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['site_id'] = $site->id;
        $validated['status'] = $validated['status'] ?? 'planned';

        $project = Project::create($validated);

        return response()->json([
            'message' => 'Project created.',
            'project' => $project,
        ], 201);
    }

    public function show(Request $request, Campaign $campaign, Project $project): JsonResponse
    {
        $this->authorize('viewAny', [Project::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $project)) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($project)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        return response()->json(['project' => $project]);
    }

    public function update(Request $request, Campaign $campaign, Project $project): JsonResponse
    {
        $this->authorize('update', [Project::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $project)) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($project)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:planned,ongoing,completed'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'impact' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:255'],
            'constituency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $project->update($validated);

        return response()->json([
            'message' => 'Project updated.',
            'project' => $project->fresh(),
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, Project $project): JsonResponse
    {
        $this->authorize('delete', [Project::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $project)) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($project)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    private function belongsToCampaign(Campaign $campaign, Project $project): bool
    {
        return $campaign->site && $project->site_id === $campaign->site->id;
    }
}
