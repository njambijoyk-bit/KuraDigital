<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactMessageController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [ContactMessage::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            return response()->json(['message' => 'No site configured for this campaign.'], 404);
        }

        $query = $site->contactMessages();

        // Apply geographic ABAC filters
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        } else {
            $query->where('is_archived', false);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->with('assignee:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($messages);
    }

    public function show(Campaign $campaign, ContactMessage $message): JsonResponse
    {
        $this->authorize('view', [ContactMessage::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $message)) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        if (!$message->is_read) {
            $message->update(['is_read' => true]);
        }

        return response()->json(['message' => $message->load('assignee:id,name')]);
    }

    public function update(Request $request, Campaign $campaign, ContactMessage $message): JsonResponse
    {
        $this->authorize('update', [ContactMessage::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $message)) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        $validated = $request->validate([
            'is_read' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'response' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($validated['response']) && $validated['response']) {
            $validated['responded_at'] = now();
        }

        $message->update($validated);

        return response()->json([
            'message' => 'Contact message updated.',
            'contact_message' => $message->fresh()->load('assignee:id,name'),
        ]);
    }

    public function destroy(Campaign $campaign, ContactMessage $message): JsonResponse
    {
        $this->authorize('delete', [ContactMessage::class, $campaign]);

        if (!$this->belongsToCampaign($campaign, $message)) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        $message->delete();

        return response()->json(['message' => 'Contact message deleted.']);
    }

    public function export(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorize('export', [ContactMessage::class, $campaign]);

        $site = $campaign->site;

        if (!$site) {
            abort(404, 'No site configured for this campaign.');
        }

        $query = $site->contactMessages();

        // Apply geographic ABAC filters for exports too
        $membership = $request->user()->membershipFor($campaign);
        if ($membership) {
            $membership->applyGeographicFilters($query, ['ward', 'constituency', 'county']);
        }

        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        $messages = $query->orderByDesc('created_at')->get();

        return response()->streamDownload(function () use ($messages) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Message', 'Read', 'Archived', 'Response', 'Responded At', 'Received At']);

            foreach ($messages as $m) {
                fputcsv($handle, [
                    $m->name, $m->email, $m->phone, $m->message,
                    $m->is_read ? 'Yes' : 'No',
                    $m->is_archived ? 'Yes' : 'No',
                    $m->response,
                    $m->responded_at?->toDateTimeString(),
                    $m->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'contact-messages-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function belongsToCampaign(Campaign $campaign, ContactMessage $message): bool
    {
        return $campaign->site && $message->site_id === $campaign->site->id;
    }
}
