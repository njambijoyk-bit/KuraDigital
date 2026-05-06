<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MpesaDarajaService
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;
    private string $environment;

    public function __construct()
    {
        $this->environment = config('services.mpesa.environment', 'sandbox');
        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
        $this->consumerKey = config('services.mpesa.consumer_key') ?? '';
        $this->consumerSecret = config('services.mpesa.consumer_secret') ?? '';
        $this->shortcode = config('services.mpesa.shortcode') ?? '';
        $this->passkey = config('services.mpesa.passkey') ?? '';
        $this->callbackUrl = config('services.mpesa.callback_url') ?? '';
    }

    public function getAccessToken(): ?string
    {
        return Cache::remember('mpesa_access_token', 3500, function () {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('M-Pesa auth failed', ['response' => $response->body()]);
            return null;
        });
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online).
     */
    public function stkPush(string $phoneNumber, float $amount, string $accountRef, string $description = 'Donation'): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Failed to obtain access token'];
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        $phone = $this->formatPhone($phoneNumber);

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $amount,
                'PartyA' => $phone,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $this->callbackUrl . '/stk-callback',
                'AccountReference' => substr($accountRef, 0, 12),
                'TransactionDesc' => substr($description, 0, 13),
            ]);

        if ($response->successful() && $response->json('ResponseCode') === '0') {
            return [
                'success' => true,
                'merchant_request_id' => $response->json('MerchantRequestID'),
                'checkout_request_id' => $response->json('CheckoutRequestID'),
            ];
        }

        Log::error('M-Pesa STK Push failed', ['response' => $response->json()]);
        return [
            'success' => false,
            'error' => $response->json('errorMessage') ?? $response->json('ResponseDescription') ?? 'STK push failed',
        ];
    }

    /**
     * Query STK Push transaction status.
     */
    public function stkQuery(string $checkoutRequestId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Failed to obtain access token'];
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'result_code' => $response->json('ResultCode'),
                'result_desc' => $response->json('ResultDesc'),
            ];
        }

        return ['success' => false, 'error' => 'Query failed'];
    }

    /**
     * Register C2B URLs (confirmation + validation).
     */
    public function registerC2bUrls(): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Failed to obtain access token'];
        }

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/c2b/v1/registerurl", [
                'ShortCode' => $this->shortcode,
                'ResponseType' => 'Completed',
                'ConfirmationURL' => $this->callbackUrl . '/c2b-confirmation',
                'ValidationURL' => $this->callbackUrl . '/c2b-validation',
            ]);

        if ($response->successful()) {
            return ['success' => true];
        }

        Log::error('M-Pesa C2B URL registration failed', ['response' => $response->json()]);
        return ['success' => false, 'error' => 'Registration failed'];
    }

    /**
     * Parse STK Push callback payload.
     */
    public function parseStkCallback(array $payload): array
    {
        $body = $payload['Body']['stkCallback'] ?? [];
        $resultCode = (string) ($body['ResultCode'] ?? '');
        $resultDesc = $body['ResultDesc'] ?? '';
        $merchantRequestId = $body['MerchantRequestID'] ?? '';
        $checkoutRequestId = $body['CheckoutRequestID'] ?? '';

        $metadata = [];
        foreach ($body['CallbackMetadata']['Item'] ?? [] as $item) {
            $metadata[$item['Name']] = $item['Value'] ?? null;
        }

        return [
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'merchant_request_id' => $merchantRequestId,
            'checkout_request_id' => $checkoutRequestId,
            'amount' => $metadata['Amount'] ?? null,
            'receipt_number' => $metadata['MpesaReceiptNumber'] ?? null,
            'phone_number' => $metadata['PhoneNumber'] ?? null,
            'transaction_date' => $metadata['TransactionDate'] ?? null,
        ];
    }

    /**
     * Parse C2B confirmation payload.
     */
    public function parseC2bConfirmation(array $payload): array
    {
        return [
            'transaction_type' => $payload['TransactionType'] ?? '',
            'transaction_id' => $payload['TransID'] ?? '',
            'amount' => (float) ($payload['TransAmount'] ?? 0),
            'phone_number' => $payload['MSISDN'] ?? '',
            'bill_ref_number' => $payload['BillRefNumber'] ?? '',
            'first_name' => $payload['FirstName'] ?? '',
            'middle_name' => $payload['MiddleName'] ?? '',
            'last_name' => $payload['LastName'] ?? '',
        ];
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return '254' . substr($phone, 1);
        }

        if (str_starts_with($phone, '+254')) {
            return substr($phone, 1);
        }

        return $phone;
    }

    public function isConfigured(): bool
    {
        return !empty($this->consumerKey)
            && !empty($this->consumerSecret)
            && !empty($this->shortcode);
    }
}
