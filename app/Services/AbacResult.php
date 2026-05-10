<?php

namespace App\Services;

class AbacResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?string $requiredRole = null,
    ) {}

    public static function allow(): self
    {
        return new self(true, 'allowed');
    }

    public static function deny(string $message, ?string $requiredRole = null): self
    {
        return new self(false, 'denied', $message, $requiredRole);
    }

    public static function warn(string $message): self
    {
        return new self(true, 'warning', $message);
    }

    public static function requireDisclosure(string $message): self
    {
        return new self(true, 'disclosure_required', $message);
    }
}
