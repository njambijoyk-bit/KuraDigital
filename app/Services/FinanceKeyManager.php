<?php

namespace App\Services;

class FinanceKeyManager
{
    private const PURPOSES = [
        'field_encryption' => 'finance-field-encrypt',
        'blind_index' => 'finance-blind-index',
        'export_encryption' => 'finance-export-encrypt',
        'request_signing' => 'finance-request-sign',
        'biometric_session' => 'finance-biometric-session',
    ];

    public function getKey(string $purpose): string
    {
        $info = self::PURPOSES[$purpose] ?? null;
        if (!$info) {
            throw new \InvalidArgumentException("Unknown key purpose: {$purpose}");
        }

        $appKey = config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return hash_hkdf('sha256', $appKey, 32, $info);
    }
}
