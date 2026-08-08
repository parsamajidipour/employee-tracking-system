<?php

namespace App\ValueObjects;

final readonly class DeviceLoginResult
{
    private function __construct(
        public bool $success,
        public ?string $token,
        public ?string $failureReason,
    ) {}

    public static function success(string $token): self
    {
        return new self(true, $token, null);
    }

    public static function invalidCredentials(): self
    {
        return new self(false, null, 'invalid_credentials');
    }

    public static function inactive(): self
    {
        return new self(false, null, 'inactive');
    }

    public static function deviceConflict(): self
    {
        return new self(false, null, 'device_conflict');
    }
}
