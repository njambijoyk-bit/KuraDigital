<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Media::class, $campaign]);

        $query = $campaign->media();

        if ($request->has('collection')) {
            $query->where('collection', $request->input('collection'));
        }

        if ($request->has('mime_type')) {
            $query->where('mime_type', 'like', $request->input('mime_type') . '%');
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        $media = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($media);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Media::class, $campaign]);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'alt_text' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $file = $request->file('file');
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $path = "campaigns/{$campaign->id}/media/{$filename}";

        Storage::disk('public')->put($path, file_get_contents($file));

        $media = Media::create([
            'campaign_id' => $campaign->id,
            'uploaded_by' => $request->user()->id,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'disk' => 'public',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'alt_text' => $validated['alt_text'] ?? null,
            'collection' => $validated['collection'] ?? 'default',
            'tags' => $validated['tags'] ?? null,
        ]);

        return response()->json([
            'message' => 'File uploaded.',
            'media' => $media,
        ], 201);
    }

    public function show(Campaign $campaign, Media $media): JsonResponse
    {
        $this->authorize('view', $media);

        if ($media->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Media not found.'], 404);
        }

        return response()->json(['media' => $media->load('uploader:id,name')]);
    }

    public function update(Request $request, Campaign $campaign, Media $media): JsonResponse
    {
        $this->authorize('update', $media);

        if ($media->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Media not found.'], 404);
        }

        $validated = $request->validate([
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collection' => ['sometimes', 'string', 'max:100'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $media->update($validated);

        return response()->json([
            'message' => 'Media updated.',
            'media' => $media->fresh(),
        ]);
    }

    public function destroy(Campaign $campaign, Media $media): JsonResponse
    {
        $this->authorize('delete', $media);

        if ($media->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Media not found.'], 404);
        }

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return response()->json(['message' => 'Media deleted.']);
    }
}
