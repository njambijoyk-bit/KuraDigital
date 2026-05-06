<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendMessageCampaign;
use App\Models\Campaign;
use App\Models\MessageCampaign;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagingController extends Controller
{
    // =====================================================================
    // Message Templates
    // =====================================================================

    public function templatesIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [MessageTemplate::class, $campaign]);

        $query = $campaign->messageTemplates()->with('creator:id,name');

        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $templates = $query->orderByDesc('updated_at')->paginate(20);

        return response()->json($templates);
    }

    public function templatesStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [MessageTemplate::class, $campaign]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:sms,whatsapp,email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*.name' => ['required_with:variables', 'string'],
            'variables.*.description' => ['nullable', 'string'],
        ]);

        $this->authorizeChannelCreate($request->user(), $campaign, $validated['channel']);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $template = MessageTemplate::create($validated);
        $template->load('creator:id,name');

        return response()->json([
            'message' => 'Template created.',
            'template' => $template,
        ], 201);
    }

    public function templatesShow(Request $request, Campaign $campaign, MessageTemplate $template): JsonResponse
    {
        $this->authorize('view', [MessageTemplate::class, $campaign]);

        if ($template->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Template not found.'], 404);
        }

        $template->load('creator:id,name', 'approver:id,name');

        return response()->json(['template' => $template]);
    }

    public function templatesUpdate(Request $request, Campaign $campaign, MessageTemplate $template): JsonResponse
    {
        $this->authorize('update', [MessageTemplate::class, $campaign]);

        if ($template->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Template not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*.name' => ['required_with:variables', 'string'],
            'variables.*.description' => ['nullable', 'string'],
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'Template updated.',
            'template' => $template,
        ]);
    }

    public function templatesDestroy(Request $request, Campaign $campaign, MessageTemplate $template): JsonResponse
    {
        $this->authorize('delete', [MessageTemplate::class, $campaign]);

        if ($template->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Template not found.'], 404);
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }

    public function templatesApprove(Request $request, Campaign $campaign, MessageTemplate $template): JsonResponse
    {
        $this->authorize('approve', [MessageTemplate::class, $campaign]);

        if ($template->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Template not found.'], 404);
        }

        $template->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Template approved.',
            'template' => $template,
        ]);
    }

    // =====================================================================
    // Message Campaigns
    // =====================================================================

    public function campaignsIndex(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [MessageCampaign::class, $campaign]);

        $query = $campaign->messageCampaigns()->with('creator:id,name');

        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->input('search')}%");
        }

        $campaigns = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($campaigns);
    }

    public function campaignsStore(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', [MessageCampaign::class, $campaign]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:sms,whatsapp,email'],
            'template_id' => ['nullable', 'exists:message_templates,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience_filters' => ['nullable', 'array'],
            'audience_filters.ward' => ['nullable', 'string'],
            'audience_filters.county' => ['nullable', 'string'],
            'audience_filters.tags' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $this->authorizeChannelCreate($request->user(), $campaign, $validated['channel']);

        $validated['campaign_id'] = $campaign->id;
        $validated['created_by'] = $request->user()->id;

        $msgCampaign = MessageCampaign::create($validated);
        $msgCampaign->load('creator:id,name');

        return response()->json([
            'message' => 'Message campaign created.',
            'message_campaign' => $msgCampaign,
        ], 201);
    }

    public function campaignsShow(Request $request, Campaign $campaign, MessageCampaign $messageCampaign): JsonResponse
    {
        $this->authorize('view', [MessageCampaign::class, $campaign]);

        if ($messageCampaign->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Message campaign not found.'], 404);
        }

        $messageCampaign->load('creator:id,name', 'template', 'approver:id,name');

        return response()->json(['message_campaign' => $messageCampaign]);
    }

    public function campaignsUpdate(Request $request, Campaign $campaign, MessageCampaign $messageCampaign): JsonResponse
    {
        $this->authorize('update', [MessageCampaign::class, $campaign]);

        if ($messageCampaign->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Message campaign not found.'], 404);
        }

        if (in_array($messageCampaign->status, ['sending', 'sent'])) {
            return response()->json(['message' => 'Cannot edit a campaign that is sending or has been sent.'], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'audience_filters' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $messageCampaign->update($validated);

        return response()->json([
            'message' => 'Message campaign updated.',
            'message_campaign' => $messageCampaign,
        ]);
    }

    public function campaignsApprove(Request $request, Campaign $campaign, MessageCampaign $messageCampaign): JsonResponse
    {
        $this->authorize('approve', [MessageCampaign::class, $campaign]);

        if ($messageCampaign->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Message campaign not found.'], 404);
        }

        if ($messageCampaign->status !== 'draft') {
            return response()->json(['message' => 'Only draft campaigns can be approved.'], 422);
        }

        $messageCampaign->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message campaign approved.',
            'message_campaign' => $messageCampaign,
        ]);
    }

    public function campaignsSend(Request $request, Campaign $campaign, MessageCampaign $messageCampaign): JsonResponse
    {
        if ($messageCampaign->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Message campaign not found.'], 404);
        }

        // Channel-specific send permission
        $user = $request->user();
        $channel = $messageCampaign->channel;
        $sendPermission = match ($channel) {
            'sms' => 'comms.send-sms',
            'whatsapp' => 'comms.send-whatsapp',
            'email' => 'comms.send-email',
            default => null,
        };

        if (!$sendPermission || !$user->campaignCan($sendPermission, $campaign)) {
            return response()->json(['message' => 'You do not have permission to send this type of message.'], 403);
        }

        if ($messageCampaign->status !== 'approved') {
            return response()->json(['message' => 'Campaign must be approved before sending.'], 422);
        }

        $messageCampaign->update([
            'status' => 'sending',
            'sent_at' => now(),
        ]);

        SendMessageCampaign::dispatch($messageCampaign->id);

        return response()->json([
            'message' => 'Message campaign queued for sending.',
            'message_campaign' => $messageCampaign,
        ]);
    }

    public function campaignsDestroy(Request $request, Campaign $campaign, MessageCampaign $messageCampaign): JsonResponse
    {
        $this->authorize('delete', [MessageCampaign::class, $campaign]);

        if ($messageCampaign->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Message campaign not found.'], 404);
        }

        if (in_array($messageCampaign->status, ['sending', 'sent'])) {
            return response()->json(['message' => 'Cannot delete a campaign that is sending or has been sent.'], 422);
        }

        $messageCampaign->delete();

        return response()->json(['message' => 'Message campaign deleted.']);
    }

    // =====================================================================
    // Audience & Delivery Logs
    // =====================================================================

    public function audienceCount(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [MessageCampaign::class, $campaign]);

        $channel = $request->input('channel', 'sms');
        $query = Voter::where('campaign_id', $campaign->id);

        $filters = $request->input('filters', []);

        if (!empty($filters['ward'])) {
            $query->where('ward', $filters['ward']);
        }
        if (!empty($filters['county'])) {
            $query->where('county', $filters['county']);
        }
        if (!empty($filters['constituency'])) {
            $query->where('constituency', $filters['constituency']);
        }
        if (!empty($filters['supporter_status'])) {
            $query->where('supporter_status', $filters['supporter_status']);
        }
        if (!empty($filters['tags'])) {
            foreach ($filters['tags'] as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }
        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (in_array($channel, ['sms', 'whatsapp'])) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        } else {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        $count = $query->count();

        $byWard = (clone $query)->selectRaw('ward, count(*) as count')
            ->groupBy('ward')
            ->pluck('count', 'ward');

        return response()->json([
            'total' => $count,
            'channel' => $channel,
            'by_ward' => $byWard,
        ]);
    }

    public function campaignLogs(Request $request, Campaign $campaign, MessageCampaign $messageCampaign): JsonResponse
    {
        $this->authorize('view', [MessageCampaign::class, $campaign]);

        if ($messageCampaign->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Message campaign not found.'], 404);
        }

        $query = $messageCampaign->logs()->with('voter:id,name,phone,email,ward');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->orderByDesc('created_at')->paginate(50);

        $stats = [
            'total' => $messageCampaign->total_recipients,
            'sent' => $messageCampaign->sent_count,
            'failed' => $messageCampaign->failed_count,
            'pending' => MessageLog::where('message_campaign_id', $messageCampaign->id)->where('status', 'pending')->count(),
            'delivered' => MessageLog::where('message_campaign_id', $messageCampaign->id)->where('status', 'delivered')->count(),
        ];

        return response()->json([
            'logs' => $logs,
            'stats' => $stats,
        ]);
    }

    public function campaignsStats(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [MessageCampaign::class, $campaign]);

        $totalCampaigns = $campaign->messageCampaigns()->count();
        $sentCampaigns = $campaign->messageCampaigns()->where('status', 'sent')->count();
        $totalSent = $campaign->messageCampaigns()->sum('sent_count');
        $totalFailed = $campaign->messageCampaigns()->sum('failed_count');
        $totalRecipients = $campaign->messageCampaigns()->sum('total_recipients');

        $byChannel = $campaign->messageCampaigns()
            ->selectRaw('channel, count(*) as campaigns, sum(sent_count) as sent, sum(failed_count) as failed')
            ->groupBy('channel')
            ->get();

        return response()->json([
            'stats' => [
                'total_campaigns' => $totalCampaigns,
                'sent_campaigns' => $sentCampaigns,
                'total_sent' => (int) $totalSent,
                'total_failed' => (int) $totalFailed,
                'total_recipients' => (int) $totalRecipients,
                'delivery_rate' => $totalRecipients > 0 ? round(($totalSent / $totalRecipients) * 100, 1) : 0,
                'by_channel' => $byChannel,
            ],
        ]);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function authorizeChannelCreate($user, Campaign $campaign, string $channel): void
    {
        $permission = match ($channel) {
            'sms' => 'comms.create-sms',
            'whatsapp' => 'comms.create-whatsapp',
            'email' => 'comms.create-email',
            default => null,
        };

        if ($permission && !$user->campaignCan($permission, $campaign)) {
            abort(403, "You do not have permission to create {$channel} messages.");
        }
    }
}
