<?php

namespace App\Services;

class FinanceEncryptionService
{
    private string $blindIndexKey;

    public function __construct(FinanceKeyManager $keyManager)
    {
        $this->blindIndexKey = $keyManager->getKey('blind_index');
    }

    public function blindIndex(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), $this->blindIndexKey);
    }
}
