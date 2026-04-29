<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Volunteer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [Volunteer::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $query = $site->volunteers();

        // Apply geographic ABAC filters
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward']);
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('engagement_status')) {
            $query->where('engagement_status', $request->input('engagement_status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('skills', 'like', "%{$search}%");
            });
        }

        $volunteers = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($volunteers);
    }

    public function show(Request $request, Campaign $campaign, Volunteer $volunteer): JsonResponse
    {
        $this->authorize('view', [Volunteer::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $volunteer)) {
            return response()->json(['message' => 'Volunteer not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($volunteer)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        return response()->json(['volunteer' => $volunteer]);
    }

    public function update(Request $request, Campaign $campaign, Volunteer $volunteer): JsonResponse
    {
        $this->authorize('update', [Volunteer::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $volunteer)) {
            return response()->json(['message' => 'Volunteer not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($volunteer)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'ward' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'max:100'],
            'skills' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'engagement_status' => ['sometimes', 'in:new,active,inactive'],
        ]);

        $volunteer->update($validated);

        return response()->json([
            'message' => 'Volunteer updated.',
            'volunteer' => $volunteer->fresh(),
        ]);
    }

    public function destroy(Request $request, Campaign $campaign, Volunteer $volunteer): JsonResponse
    {
        $this->authorize('delete', [Volunteer::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $volunteer)) {
            return response()->json(['message' => 'Volunteer not found.'], 404);
        }

        $membership = $request->user()->membershipFor($campaign);
        if ($membership && !$membership->hasGeographicAccessTo($volunteer)) {
            return response()->json(['message' => 'You do not have access to this geographic area.'], 403);
        }

        $volunteer->delete();

        return response()->json(['message' => 'Volunteer deleted.']);
    }

    public function export(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorize('export', [Volunteer::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            abort(404, 'No site configured for this campaign.');
        }

        $query = $site->volunteers();

        // Apply geographic ABAC filters
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward']);
        }

        if ($request->has('ward')) {
            $query->where('ward', $request->input('ward'));
        }

        if ($request->has('engagement_status')) {
            $query->where('engagement_status', $request->input('engagement_status'));
        }

        $volunteers = $query->orderByDesc('created_at')->get();

        return response()->streamDownload(function () use ($volunteers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Phone', 'Email', 'Ward', 'Role', 'Skills', 'Status', 'Signed Up']);

            foreach ($volunteers as $v) {
                fputcsv($handle, [
                    $v->name, $v->phone, $v->email, $v->ward,
                    $v->role, $v->skills, $v->engagement_status,
                    $v->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'volunteers-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function belongsToCampaign(Campaign $campaign, Volunteer $volunteer): bool
    {
        return $campaign->site && $volunteer->site_id === $campaign->site->id;
    }
}
