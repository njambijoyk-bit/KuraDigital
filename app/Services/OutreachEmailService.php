<?php

namespace App\Services;

use App\Mail\CampaignOutreach;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OutreachEmailService
{
    public function send(string $to, string $subject, string $body, string $campaignName = ''): array
    {
        try {
            $bodyHtml = nl2br(e($body));

            Mail::to($to)->send(new CampaignOutreach(
                subject: $subject,
                bodyHtml: $bodyHtml,
                campaignName: $campaignName,
            ));

            return [
                'success' => true,
                'provider' => config('mail.default', 'smtp'),
            ];
        } catch (\Exception $e) {
            Log::error('Outreach email error', ['to' => $to, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => config('mail.default', 'smtp'),
            ];
        }
    }

    public function isConfigured(): bool
    {
        $mailer = config('mail.default', 'smtp');

        return match ($mailer) {
            'mailgun' => !empty(config('services.mailgun.secret')),
            'smtp' => !empty(config('mail.mailers.smtp.host')),
            'ses' => !empty(config('services.ses.key')),
            default => true,
        };
    }
}
