<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class SubscriptionPortalSessionResult
{
    private function __construct(
        public bool $success,
        public ?string $url = null,
        public ?string $sessionId = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(string $sessionId, string $url): self
    {
        return new self(success: true, url: $url, sessionId: $sessionId);
    }

    public static function failure(string $message): self
    {
        return new self(success: false, errorMessage: $message);
    }
}
