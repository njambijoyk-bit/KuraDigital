<?php

namespace App\Services;

class EncryptedExportService
{
    public function encryptCsv(string $csvContent, string $password): string
    {
        $salt = random_bytes(16);
        $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($csvContent, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        // Pack: salt (16) + iv (16) + ciphertext
        return base64_encode($salt . $iv . $encrypted);
    }

    public function decryptCsv(string $encryptedBase64, string $password): string
    {
        $data = base64_decode($encryptedBase64);
        $salt = substr($data, 0, 16);
        $iv = substr($data, 16, 32);
        $ciphertext = substr($data, 32);

        $key = hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed — wrong password or corrupted data.');
        }

        return $decrypted;
    }
}
