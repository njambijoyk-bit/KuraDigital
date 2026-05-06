<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSmsService
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct()
    {
        $this->accountSid = config('services.twilio.account_sid', '');
        $this->authToken = config('services.twilio.auth_token', '');
        $this->fromNumber = config('services.twilio.from_number', '');
    }

    public function send(string $to, string $message): array
    {
        $to = $this->normalizePhone($to);

        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post(
                    "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json",
                    [
                        'To' => $to,
                        'From' => $this->fromNumber,
                        'Body' => $message,
                    ]
                );

            $body = $response->json();

            if ($response->successful() && isset($body['sid'])) {
                return [
                    'success' => true,
                    'external_id' => $body['sid'],
                ];
            }

            return [
                'success' => false,
                'error' => $body['message'] ?? $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS error', ['to' => $to, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->accountSid) && !empty($this->authToken) && !empty($this->fromNumber);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '+254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '254') && !str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
