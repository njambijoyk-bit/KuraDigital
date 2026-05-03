<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFieldMedia;
use App\Models\Campaign;
use App\Models\FieldAgent;
use App\Models\FieldReport;
use App\Models\FieldReportMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FieldReportController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [FieldReport::class, $campaign]);

        $query = $campaign->fieldReports()->with(['user:id,name', 'media']);

        // ABAC geographic filtering
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('captured_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('captured_at', '<=', $request->input('date_to'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->input('has_media') === 'true') {
            $query->has('media');
        }

        $reports = $query->withCount('media')
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($reports);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [FieldReport::class, $campaign]);

        $validated = $request->validate([
            'type' => ['required', 'in:photo,video,audio,text'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ward' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'captured_at' => ['nullable', 'date'],
            'client_id' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:102400'], // 100MB max per file
        ]);

        // Dedup: if client_id already exists for this campaign, return existing
        if (!empty($validated['client_id'])) {
            $existing = FieldReport::where('campaign_id', $campaign->id)
                ->where('client_id', $validated['client_id'])
                ->with(['user:id,name', 'media'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Report already exists.',
                    'field_report' => $existing,
                    'deduplicated' => true,
                ]);
            }
        }

        // Link to field agent if one exists for this user
        $agent = FieldAgent::where('campaign_id', $campaign->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $report = DB::transaction(function () use ($validated, $campaign, $request, $agent) {
            $report = FieldReport::create([
                'campaign_id' => $campaign->id,
                'user_id' => $request->user()->id,
                'field_agent_id' => $agent?->id,
                'type' => $validated['type'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'ward' => $validated['ward'] ?? null,
                'constituency' => $validated['constituency'] ?? null,
                'county' => $validated['county'] ?? null,
                'status' => $validated['type'] === 'text' ? 'processed' : 'submitted',
                'captured_at' => $validated['captured_at'] ?? now(),
                'client_id' => $validated['client_id'] ?? null,
                'tags' => $validated['tags'] ?? null,
            ]);

            // Handle file uploads
            if ($request->hasFile('files')) {
                $this->storeFiles($report, $request->file('files'));
            }

            return $report;
        });

        // Update agent's last_active_at
        if ($agent) {
            $agent->update(['last_active_at' => now()]);
        }

        $report->load(['user:id,name', 'media']);

        return response()->json([
            'message' => 'Field report created.',
            'field_report' => $report,
        ], 201);
    }

    public function show(Request $request, Campaign $campaign, FieldReport $fieldReport): JsonResponse
    {
        if ($fieldReport->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $this->authorize('view', [FieldReport::class, $campaign, $fieldReport]);

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($fieldReport)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $fieldReport->load(['user:id,name', 'fieldAgent:id,agent_code,ward,constituency', 'media']);

        return response()->json(['field_report' => $fieldReport]);
    }

    public function update(Request $request, Campaign $campaign, FieldReport $fieldReport): JsonResponse
    {
        if ($fieldReport->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $this->authorize('update', [FieldReport::class, $campaign, $fieldReport]);

        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:draft,submitted,processing,processed,flagged'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $fieldReport->update($validated);
        $fieldReport->load(['user:id,name', 'media']);

        return response()->json([
            'message' => 'Field report updated.',
            'field_report' => $fieldReport,
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, FieldReport $fieldReport): JsonResponse
    {
        if ($fieldReport->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $this->authorize('delete', [FieldReport::class, $campaign, $fieldReport]);

        // Delete media files from storage
        foreach ($fieldReport->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
            if ($media->thumbnail_url) {
                $thumbnailPath = str_replace('/storage/', '', parse_url($media->thumbnail_url, PHP_URL_PATH));
                Storage::disk($media->disk)->delete($thumbnailPath);
            }
        }

        $fieldReport->delete();

        return response()->json(['message' => 'Field report deleted.']);
    }

    public function stats(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [FieldReport::class, $campaign]);

        $query = $campaign->fieldReports();

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'by_type' => [
                'photo' => (clone $query)->where('type', 'photo')->count(),
                'video' => (clone $query)->where('type', 'video')->count(),
                'audio' => (clone $query)->where('type', 'audio')->count(),
                'text' => (clone $query)->where('type', 'text')->count(),
            ],
            'by_status' => [
                'submitted' => (clone $query)->where('status', 'submitted')->count(),
                'processing' => (clone $query)->where('status', 'processing')->count(),
                'processed' => (clone $query)->where('status', 'processed')->count(),
                'flagged' => (clone $query)->where('status', 'flagged')->count(),
            ],
            'today' => (clone $query)->whereDate('captured_at', today())->count(),
            'this_week' => (clone $query)->whereBetween('captured_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];

        return response()->json(['stats' => $stats]);
    }

    public function sync(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [FieldReport::class, $campaign]);

        $validated = $request->validate([
            'reports' => ['required', 'array', 'max:50'],
            'reports.*.type' => ['required', 'in:photo,video,audio,text'],
            'reports.*.title' => ['nullable', 'string', 'max:255'],
            'reports.*.body' => ['nullable', 'string'],
            'reports.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'reports.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'reports.*.ward' => ['nullable', 'string', 'max:255'],
            'reports.*.constituency' => ['nullable', 'string', 'max:255'],
            'reports.*.county' => ['nullable', 'string', 'max:255'],
            'reports.*.captured_at' => ['nullable', 'date'],
            'reports.*.client_id' => ['required', 'string', 'max:100'],
            'reports.*.tags' => ['nullable', 'array'],
            'reports.*.tags.*' => ['string'],
        ]);

        $agent = FieldAgent::where('campaign_id', $campaign->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $results = [];

        DB::transaction(function () use ($validated, $campaign, $request, $agent, &$results) {
            foreach ($validated['reports'] as $reportData) {
                $existing = FieldReport::where('campaign_id', $campaign->id)
                    ->where('client_id', $reportData['client_id'])
                    ->first();

                if ($existing) {
                    $results[] = [
                        'client_id' => $reportData['client_id'],
                        'status' => 'skipped',
                        'field_report_id' => $existing->id,
                    ];
                    continue;
                }

                $report = FieldReport::create([
                    'campaign_id' => $campaign->id,
                    'user_id' => $request->user()->id,
                    'field_agent_id' => $agent?->id,
                    'type' => $reportData['type'],
                    'title' => $reportData['title'] ?? null,
                    'body' => $reportData['body'] ?? null,
                    'latitude' => $reportData['latitude'] ?? null,
                    'longitude' => $reportData['longitude'] ?? null,
                    'ward' => $reportData['ward'] ?? null,
                    'constituency' => $reportData['constituency'] ?? null,
                    'county' => $reportData['county'] ?? null,
                    'status' => $reportData['type'] === 'text' ? 'processed' : 'submitted',
                    'captured_at' => $reportData['captured_at'] ?? now(),
                    'client_id' => $reportData['client_id'],
                    'tags' => $reportData['tags'] ?? null,
                ]);

                $results[] = [
                    'client_id' => $reportData['client_id'],
                    'status' => 'created',
                    'field_report_id' => $report->id,
                ];
            }
        });

        if ($agent) {
            $agent->update(['last_active_at' => now()]);
        }

        return response()->json([
            'message' => 'Sync completed.',
            'results' => $results,
        ]);
    }

    private function storeFiles(FieldReport $report, array $files): void
    {
        $disk = config('filesystems.default') === 'local' ? 'public' : 'cdn';

        foreach ($files as $index => $file) {
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(40) . '.' . $extension;
            $path = "campaigns/{$report->campaign_id}/field-reports/{$report->id}/{$filename}";

            Storage::disk($disk)->put($path, file_get_contents($file));

            $media = FieldReportMedia::create([
                'field_report_id' => $report->id,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'disk' => $disk,
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
                'sort_order' => $index,
            ]);

            ProcessFieldMedia::dispatch($media->id);
        }
    }

    public function reprocess(Request $request, Campaign $campaign, FieldReport $fieldReport): JsonResponse
    {
        if ($fieldReport->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $this->authorize('update', [FieldReport::class, $campaign, $fieldReport]);

        $mediaItems = $fieldReport->media;
        if ($mediaItems->isEmpty()) {
            return response()->json(['message' => 'No media to process.'], 422);
        }

        foreach ($mediaItems as $media) {
            $media->update([
                'processing_status' => 'pending',
                'processing_result' => null,
            ]);
            ProcessFieldMedia::dispatch($media->id);
        }

        $fieldReport->update(['status' => 'submitted']);

        return response()->json([
            'message' => 'Reprocessing started for ' . $mediaItems->count() . ' file(s).',
        ]);
    }
}
