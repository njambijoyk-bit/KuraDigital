<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsService
{
    private string $apiKey;
    private string $username;
    private string $baseUrl;
    private string $senderId;

    public function __construct()
    {
        $this->apiKey = config('services.africastalking.api_key', '');
        $this->username = config('services.africastalking.username', 'sandbox');
        $this->senderId = config('services.africastalking.sender_id', '');
        $this->baseUrl = $this->username === 'sandbox'
            ? 'https://api.sandbox.africastalking.com'
            : 'https://api.africastalking.com';
    }

    public function send(string $to, string $message): array
    {
        $payload = [
            'username' => $this->username,
            'to' => $this->normalizePhone($to),
            'message' => $message,
        ];

        if ($this->senderId) {
            $payload['from'] = $this->senderId;
        }

        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])->asForm()->post("{$this->baseUrl}/version1/messaging", $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['SMSMessageData']['Recipients'])) {
                $recipient = $body['SMSMessageData']['Recipients'][0] ?? null;

                if ($recipient && $recipient['statusCode'] === 101) {
                    return [
                        'success' => true,
                        'external_id' => $recipient['messageId'] ?? null,
                        'cost' => $recipient['cost'] ?? null,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $recipient['status'] ?? 'Unknown delivery error',
                    'external_id' => $recipient['messageId'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $body['SMSMessageData']['Message'] ?? $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('AfricasTalking SMS error', ['to' => $to, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->username);
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
