<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsGateway
{
    private AfricasTalkingSmsService $africastalking;
    private TwilioSmsService $twilio;

    public function __construct(AfricasTalkingSmsService $africastalking, TwilioSmsService $twilio)
    {
        $this->africastalking = $africastalking;
        $this->twilio = $twilio;
    }

    public function send(string $to, string $message): array
    {
        // Primary: Africa's Talking
        if ($this->africastalking->isConfigured()) {
            $result = $this->africastalking->send($to, $message);

            if ($result['success']) {
                return array_merge($result, ['provider' => 'africastalking']);
            }

            Log::warning('AfricasTalking failed, attempting Twilio fallback', [
                'to' => $to,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }

        // Fallback: Twilio
        if ($this->twilio->isConfigured()) {
            $result = $this->twilio->send($to, $message);

            return array_merge($result, ['provider' => 'twilio']);
        }

        return [
            'success' => false,
            'error' => 'No SMS provider configured. Set AFRICASTALKING_API_KEY or TWILIO_ACCOUNT_SID in .env.',
            'provider' => null,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->africastalking->isConfigured() || $this->twilio->isConfigured();
    }
}
