<?php

namespace App\Services;

class TotpService
{
    private int $digits = 6;
    private int $period = 30;
    private int $window = 1;

    public function generateSecret(int $length = 20): string
    {
        $bytes = random_bytes($length);

        return rtrim(self::base32Encode($bytes), '=');
    }

    public function verify(string $secret, string $otp): bool
    {
        $timestamp = time();

        for ($i = -$this->window; $i <= $this->window; $i++) {
            $counter = (int) floor(($timestamp + ($i * $this->period)) / $this->period);
            if (hash_equals($this->generateOtp($secret, $counter), $otp)) {
                return true;
            }
        }

        return false;
    }

    public function provisioningUri(string $secret, string $email, string $issuer = 'KuraDigital'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
            $this->digits,
            $this->period
        );
    }

    private function generateOtp(string $secret, int $counter): string
    {
        $binaryKey = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0, $counter);

        $hash = hash_hmac('sha1', $binaryCounter, $binaryKey, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % pow(10, $this->digits);

        return str_pad((string) $code, $this->digits, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split(strtoupper($data)) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue;
            }
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $decoded .= chr(bindec($byte));
        }

        return $decoded;
    }
}
