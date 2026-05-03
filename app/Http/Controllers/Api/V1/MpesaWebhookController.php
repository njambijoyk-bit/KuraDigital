<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\MpesaTransaction;
use App\Services\MpesaDarajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaWebhookController extends Controller
{
    public function stkCallback(Request $request): JsonResponse
    {
        $mpesa = app(MpesaDarajaService::class);
        $data = $mpesa->parseStkCallback($request->all());

        Log::info('M-Pesa STK Callback', $data);

        $transaction = MpesaTransaction::where('checkout_request_id', $data['checkout_request_id'])->first();

        if (!$transaction) {
            Log::warning('M-Pesa callback for unknown checkout request', $data);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($data['result_code'] === '0') {
            $transaction->update([
                'status' => 'completed',
                'receipt_number' => $data['receipt_number'],
                'result_code' => $data['result_code'],
                'result_desc' => $data['result_desc'],
                'raw_callback' => $request->all(),
            ]);

            $donation = Donation::create([
                'campaign_id' => $transaction->campaign_id,
                'donor_phone' => $data['phone_number'],
                'amount' => $data['amount'],
                'currency' => 'KES',
                'channel' => 'mpesa',
                'mpesa_receipt' => $data['receipt_number'],
                'transaction_id' => $data['checkout_request_id'],
                'status' => 'completed',
                'donated_at' => now(),
            ]);

            $transaction->update(['donation_id' => $donation->id]);
        } else {
            $transaction->update([
                'status' => $data['result_code'] === '1032' ? 'cancelled' : 'failed',
                'result_code' => $data['result_code'],
                'result_desc' => $data['result_desc'],
                'raw_callback' => $request->all(),
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function c2bValidation(Request $request): JsonResponse
    {
        Log::info('M-Pesa C2B Validation', $request->all());
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function c2bConfirmation(Request $request): JsonResponse
    {
        $mpesa = app(MpesaDarajaService::class);
        $data = $mpesa->parseC2bConfirmation($request->all());

        Log::info('M-Pesa C2B Confirmation', $data);

        $campaignId = null;
        if (!empty($data['bill_ref_number']) && str_starts_with($data['bill_ref_number'], 'KURA-')) {
            $campaignId = (int) str_replace('KURA-', '', $data['bill_ref_number']);
        }

        $donorName = trim(implode(' ', array_filter([
            $data['first_name'], $data['middle_name'], $data['last_name'],
        ])));

        $donation = Donation::create([
            'campaign_id' => $campaignId,
            'donor_name' => $donorName ?: null,
            'donor_phone' => $data['phone_number'],
            'amount' => $data['amount'],
            'currency' => 'KES',
            'channel' => 'mpesa',
            'mpesa_receipt' => $data['transaction_id'],
            'transaction_id' => $data['transaction_id'],
            'status' => 'completed',
            'donated_at' => now(),
        ]);

        MpesaTransaction::create([
            'campaign_id' => $campaignId,
            'donation_id' => $donation->id,
            'transaction_type' => 'c2b',
            'receipt_number' => $data['transaction_id'],
            'amount' => $data['amount'],
            'phone_number' => $data['phone_number'],
            'result_code' => '0',
            'result_desc' => 'Completed',
            'raw_callback' => $request->all(),
            'status' => 'completed',
        ]);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
