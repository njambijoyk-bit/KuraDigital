<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $query = $site->allNewsArticles();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $articles = $query->with('author:id,name')->paginate(20);

        return response()->json($articles);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
            'status' => ['nullable', 'in:draft,published,scheduled'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['site_id'] = $site->id;
        $validated['author_id'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'draft';
        $validated['is_published'] = $validated['status'] === 'published';

        $article = NewsArticle::create($validated);

        return response()->json([
            'message' => 'News article created.',
            'article' => $article->load('author:id,name'),
        ], 201);
    }

    public function show(Campaign $campaign, NewsArticle $article): JsonResponse
    {
        if (!$this->belongsToCampaign($campaign, $article)) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        return response()->json(['article' => $article->load('author:id,name')]);
    }

    public function update(Request $request, Campaign $campaign, NewsArticle $article): JsonResponse
    {
        if (!$this->belongsToCampaign($campaign, $article)) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'body' => ['sometimes', 'nullable', 'string'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:draft,published,scheduled'],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        if (isset($validated['status'])) {
            $validated['is_published'] = $validated['status'] === 'published';
        }

        $article->update($validated);

        return response()->json([
            'message' => 'News article updated.',
            'article' => $article->fresh()->load('author:id,name'),
        ]);
    }

    public function destroy(Campaign $campaign, NewsArticle $article): JsonResponse
    {
        if (!$this->belongsToCampaign($campaign, $article)) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        $article->delete();

        return response()->json(['message' => 'News article deleted.']);
    }

    private function belongsToCampaign(Campaign $campaign, NewsArticle $article): bool
    {
        return $campaign->site && $article->site_id === $campaign->site->id;
    }
}
