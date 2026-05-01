<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoterController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Voter::class, $campaign]);

        $query = $campaign->voters();

        // Apply geographic ABAC filters
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('supporter_status')) {
            $query->where('supporter_status', $request->input('supporter_status'));
        }

        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('constituency')) {
            $query->where('constituency', $request->input('constituency'));
        }

        if ($request->has('county')) {
            $query->where('county', $request->input('county'));
        }

        if ($request->has('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        if ($request->has('tag')) {
            $tag = $request->input('tag');
            $query->whereJsonContains('tags', $tag);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('polling_station', 'like', "%{$search}%");
            });
        }

        $voters = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($voters);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [Voter::class, $campaign]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'supporter_status' => ['nullable', 'in:supporter,leaning,undecided,opposition,unknown'],
            'source' => ['nullable', 'in:walk_in,field_agent,import,event,referral,online,other'],
            'county' => ['nullable', 'string', 'max:255'],
            'constituency' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'polling_station' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'notes' => ['nullable', 'string'],
            'gender' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
        ]);

        $validated['campaign_id'] = $campaign->id;
        $validated['registered_by'] = $request->user()->id;

        if (!isset($validated['source'])) {
            $validated['source'] = 'other';
        }

        $voter = Voter::create($validated);

        return response()->json([
            'message' => 'Voter added.',
            'voter' => $voter,
        ], 201);
    }

    public function show(Request $request, Campaign $campaign, Voter $voter): JsonResponse
    {
        $this->authorize('view', [Voter::class, $campaign]);

        if ($voter->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Voter not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($voter)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $voter->load('registeredBy:id,name');

        return response()->json(['voter' => $voter]);
    }

    public function update(Request $request, Campaign $campaign, Voter $voter): JsonResponse
    {
        $this->authorize('update', [Voter::class, $campaign]);

        if ($voter->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Voter not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($voter)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'supporter_status' => ['sometimes', 'in:supporter,leaning,undecided,opposition,unknown'],
            'source' => ['sometimes', 'in:walk_in,field_agent,import,event,referral,online,other'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'constituency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:255'],
            'polling_station' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:50'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'last_contacted_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $voter->update($validated);

        return response()->json([
            'message' => 'Voter updated.',
            'voter' => $voter->fresh(),
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, Voter $voter): JsonResponse
    {
        $this->authorize('delete', [Voter::class, $campaign]);

        if ($voter->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Voter not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($voter)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $voter->delete();

        return response()->json(['message' => 'Voter deleted.']);
    }

    public function stats(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Voter::class, $campaign]);

        $query = $campaign->voters();

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        $total = (clone $query)->count();

        $byStatus = (clone $query)
            ->select('supporter_status', DB::raw('count(*) as count'))
            ->groupBy('supporter_status')
            ->pluck('count', 'supporter_status');

        $bySource = (clone $query)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source');

        $byWard = (clone $query)
            ->whereNotNull('ward')
            ->select('ward', DB::raw('count(*) as count'))
            ->groupBy('ward')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'ward');

        $recentCount = (clone $query)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return response()->json([
            'total' => $total,
            'by_status' => $byStatus,
            'by_source' => $bySource,
            'by_ward' => $byWard,
            'added_last_7_days' => $recentCount,
        ]);
    }

    public function bulkTag(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', [Voter::class, $campaign]);

        $validated = $request->validate([
            'voter_ids' => ['required', 'array', 'min:1'],
            'voter_ids.*' => ['integer'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['string'],
            'action' => ['required', 'in:add,remove'],
        ]);

        $voters = $campaign->voters()
            ->whereIn('id', $validated['voter_ids'])
            ->get();

        $updated = 0;
        foreach ($voters as $voter) {
            $currentTags = $voter->tags ?? [];

            if ($validated['action'] === 'add') {
                $currentTags = array_unique(array_merge($currentTags, $validated['tags']));
            } else {
                $currentTags = array_values(array_diff($currentTags, $validated['tags']));
            }

            $voter->update(['tags' => $currentTags]);
            $updated++;
        }

        return response()->json([
            'message' => "Tags updated for {$updated} voter(s).",
            'updated' => $updated,
        ]);
    }

    public function bulkUpdateStatus(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', [Voter::class, $campaign]);

        $validated = $request->validate([
            'voter_ids' => ['required', 'array', 'min:1'],
            'voter_ids.*' => ['integer'],
            'supporter_status' => ['required', 'in:supporter,leaning,undecided,opposition,unknown'],
        ]);

        $updated = $campaign->voters()
            ->whereIn('id', $validated['voter_ids'])
            ->update(['supporter_status' => $validated['supporter_status']]);

        return response()->json([
            'message' => "Status updated for {$updated} voter(s).",
            'updated' => $updated,
        ]);
    }

    public function import(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('import', [Voter::class, $campaign]);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            return response()->json(['message' => 'Could not read file.'], 422);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json(['message' => 'Empty or invalid CSV file.'], 422);
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $allowedColumns = [
            'name', 'phone', 'national_id', 'email', 'supporter_status',
            'source', 'county', 'constituency', 'ward', 'polling_station',
            'gender', 'notes',
        ];

        if (!in_array('name', $header)) {
            fclose($handle);
            return response()->json(['message' => 'CSV must contain a "name" column.'], 422);
        }

        $imported = 0;
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $data = [];

            foreach ($header as $i => $col) {
                if (in_array($col, $allowedColumns) && isset($row[$i])) {
                    $data[$col] = trim($row[$i]);
                }
            }

            if (empty($data['name'])) {
                $errors[] = "Line {$lineNumber}: missing name.";
                continue;
            }

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'national_id' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'supporter_status' => ['nullable', 'in:supporter,leaning,undecided,opposition,unknown'],
                'source' => ['nullable', 'in:walk_in,field_agent,import,event,referral,online,other'],
                'county' => ['nullable', 'string', 'max:255'],
                'constituency' => ['nullable', 'string', 'max:255'],
                'ward' => ['nullable', 'string', 'max:255'],
                'polling_station' => ['nullable', 'string', 'max:255'],
                'gender' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Line {$lineNumber}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            $data['campaign_id'] = $campaign->id;
            $data['registered_by'] = $request->user()->id;
            $data['source'] = $data['source'] ?? 'import';

            Voter::create($data);
            $imported++;
        }

        fclose($handle);

        return response()->json([
            'message' => "{$imported} voter(s) imported successfully.",
            'imported' => $imported,
            'errors' => array_slice($errors, 0, 50),
            'total_errors' => count($errors),
        ], $imported > 0 ? 201 : 422);
    }

    public function export(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorize('export', [Voter::class, $campaign]);

        $query = $campaign->voters();

        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('supporter_status')) {
            $query->where('supporter_status', $request->input('supporter_status'));
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        $voters = $query->orderByDesc('created_at')->get();

        return response()->streamDownload(function () use ($voters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Name', 'Phone', 'Email', 'National ID', 'Status',
                'Source', 'County', 'Constituency', 'Ward', 'Polling Station',
                'Gender', 'Tags', 'Notes', 'Added',
            ]);

            foreach ($voters as $v) {
                fputcsv($handle, [
                    $v->name, $v->phone, $v->email, $v->national_id,
                    $v->supporter_status, $v->source,
                    $v->county, $v->constituency, $v->ward, $v->polling_station,
                    $v->gender,
                    is_array($v->tags) ? implode(', ', $v->tags) : '',
                    $v->notes,
                    $v->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'voters-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
