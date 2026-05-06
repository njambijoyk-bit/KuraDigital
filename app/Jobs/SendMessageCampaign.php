<?php

namespace App\Jobs;

use App\Models\MessageCampaign;
use App\Models\MessageLog;
use App\Models\Voter;
use App\Services\OutreachEmailService;
use App\Services\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessageCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        private int $messageCampaignId,
    ) {
        $this->queue = 'outreach';
    }

    public function handle(SmsGateway $smsGateway, OutreachEmailService $emailService): void
    {
        $msgCampaign = MessageCampaign::with('campaign')->find($this->messageCampaignId);

        if (!$msgCampaign || $msgCampaign->status !== 'sending') {
            return;
        }

        $voters = $this->resolveAudience($msgCampaign);
        $totalRecipients = $voters->count();

        $msgCampaign->update(['total_recipients' => $totalRecipients]);

        if ($totalRecipients === 0) {
            $msgCampaign->update(['status' => 'sent', 'sent_at' => now()]);
            return;
        }

        $sentCount = 0;
        $failedCount = 0;
        $campaignName = $msgCampaign->campaign->name ?? '';

        foreach ($voters as $voter) {
            $recipient = $this->getRecipientAddress($voter, $msgCampaign->channel);

            if (!$recipient) {
                $failedCount++;
                $this->logMessage($msgCampaign, $voter, $recipient ?? '', 'failed', null, 'No valid recipient address');
                continue;
            }

            $body = $this->interpolateVariables($msgCampaign->body, $voter);
            $subject = $msgCampaign->subject ? $this->interpolateVariables($msgCampaign->subject, $voter) : '';

            $result = match ($msgCampaign->channel) {
                'sms', 'whatsapp' => $smsGateway->send($recipient, $body),
                'email' => $emailService->send($recipient, $subject, $body, $campaignName),
                default => ['success' => false, 'error' => "Unsupported channel: {$msgCampaign->channel}"],
            };

            if ($result['success']) {
                $sentCount++;
                $this->logMessage($msgCampaign, $voter, $recipient, 'sent', $result['external_id'] ?? null);
                $voter->update(['last_contacted_at' => now()]);
            } else {
                $failedCount++;
                $this->logMessage($msgCampaign, $voter, $recipient, 'failed', $result['external_id'] ?? null, $result['error'] ?? null);
            }

            // Update progress every 50 messages
            if (($sentCount + $failedCount) % 50 === 0) {
                $msgCampaign->update([
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                ]);
            }
        }

        $msgCampaign->update([
            'status' => 'sent',
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'sent_at' => now(),
        ]);

        Log::info('Message campaign sent', [
            'campaign_id' => $msgCampaign->id,
            'total' => $totalRecipients,
            'sent' => $sentCount,
            'failed' => $failedCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $msgCampaign = MessageCampaign::find($this->messageCampaignId);
        if ($msgCampaign) {
            $msgCampaign->update(['status' => 'failed']);
        }

        Log::error('SendMessageCampaign job failed', [
            'campaign_id' => $this->messageCampaignId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function resolveAudience(MessageCampaign $msgCampaign): \Illuminate\Support\Collection
    {
        $query = Voter::where('campaign_id', $msgCampaign->campaign_id);

        $filters = $msgCampaign->audience_filters ?? [];

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

        // For SMS/WhatsApp require phone, for email require email
        if (in_array($msgCampaign->channel, ['sms', 'whatsapp'])) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        } else {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        return $query->get();
    }

    private function getRecipientAddress(Voter $voter, string $channel): ?string
    {
        return match ($channel) {
            'sms', 'whatsapp' => $voter->phone ?: null,
            'email' => $voter->email ?: null,
            default => null,
        };
    }

    private function interpolateVariables(string $text, Voter $voter): string
    {
        $replacements = [
            '{{name}}' => $voter->name ?? '',
            '{{first_name}}' => explode(' ', $voter->name ?? '')[0],
            '{{phone}}' => $voter->phone ?? '',
            '{{email}}' => $voter->email ?? '',
            '{{county}}' => $voter->county ?? '',
            '{{constituency}}' => $voter->constituency ?? '',
            '{{ward}}' => $voter->ward ?? '',
            '{{polling_station}}' => $voter->polling_station ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function logMessage(
        MessageCampaign $msgCampaign,
        Voter $voter,
        string $recipient,
        string $status,
        ?string $externalId = null,
        ?string $error = null,
    ): void {
        MessageLog::create([
            'message_campaign_id' => $msgCampaign->id,
            'voter_id' => $voter->id,
            'recipient' => $recipient,
            'channel' => $msgCampaign->channel,
            'status' => $status,
            'external_id' => $externalId,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
