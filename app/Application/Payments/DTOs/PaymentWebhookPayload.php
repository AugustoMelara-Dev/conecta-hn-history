<?php

declare(strict_types=1);

namespace App\Application\Payments\DTOs;

final readonly class PaymentWebhookPayload
{
    public function __construct(
        public string $eventType,
        public array $rawPayload,
        public string $capturedAt
    ) {}

    public static function fromRequest(string $type, array $data): self
    {
        // Future validation logic belongs here (e.g. asserting non-empty ID)
        return new self(
            eventType: $type,
            rawPayload: $data,
            capturedAt: $data['captured_at'] ?? null
        );
    }
}
