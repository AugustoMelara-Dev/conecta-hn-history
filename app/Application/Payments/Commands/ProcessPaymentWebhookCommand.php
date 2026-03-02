<?php

declare(strict_types=1);

namespace App\Application\Payments\Commands;

use App\Domain\Exceptions\Payment\InvalidWebhookPayloadException;

final readonly class ProcessPaymentWebhookCommand
{
    public function __construct(
        public string $eventType,
        public array $payload,
        public string $tenantId, // New Invariant
        public string $receivedAt,
        public int $schemaVersion = 1,
    ) {}

    public static function fromRequest(string $eventType, array $payload): self
    {
        // Strategy: Look for tenant_id in standard locations (metadata, custom_fields)
        $tenantId = $payload['data']['object']['metadata']['tenant_id'] 
                 ?? $payload['meta']['tenant_id'] 
                 ?? null;

        if (empty($tenantId) || !is_string($tenantId)) {
            throw InvalidWebhookPayloadException::missingTenant();
        }

        return new self(
            eventType: $eventType,
            payload: $payload,
            tenantId: $tenantId,
            receivedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        );
    }
}
