<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects\Payment;

use InvalidArgumentException;

final readonly class PaymentWebhook
{
    private function __construct(
        public string $eventType,
        public string $tenantId,
        public string $providerId,
        /** @var array<string, mixed> */
        public array $payload,
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
            providerId: (string) $providerId,
            payload: $data,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->eventType === 'payment.success';
    }

    public function paymentReference(): string
    {
        return (string) (
            $this->payload['data']['transaction_id']
            ?? $this->payload['data']['object']['id']
            ?? $this->providerId
        );
    }

    public function amountInCents(): int
    {
        return (int) (
            $this->payload['amount']
            ?? $this->payload['data']['amount']
            ?? $this->payload['data']['object']['amount']
            ?? 0
        );
    }

    public function currency(): string
    {
        return (string) (
            $this->payload['currency']
            ?? $this->payload['data']['currency']
            ?? $this->payload['data']['object']['currency']
            ?? 'HNL'
        );
    }

    public function providerName(): string
    {
        return (string) (
            $this->payload['provider']
            ?? $this->payload['meta']['provider']
            ?? 'payment_gateway'
        );
    }
}
