<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects\Payment;

use InvalidArgumentException;

final readonly class PaymentWebhook
{
    private function __construct(
        public string $eventType,
        public string $tenantId,
        public string $providerId
    ) {}

    public static function fromArray(string $type, array $data): self
    {
        // 1. Solve "Anemic Domain": Encapsulate validation logic here.
        $tenantId = $data['meta']['tenant_id'] 
                 ?? $data['data']['object']['metadata']['tenant_id'] 
                 ?? null;

        if (empty($tenantId) || !is_string($tenantId)) {
            throw new InvalidArgumentException("Payment Webhook rejected: Missing Tenant ID.");
        }

        $providerId = $data['id'] ?? $data['data']['object']['id'] ?? 'unknown';

        return new self(
            eventType: $type,
            tenantId: $tenantId,
            providerId: (string) $providerId
        );
    }

    public function isSuccessful(): bool
    {
        return $this->eventType === 'payment.success';
    }
}
