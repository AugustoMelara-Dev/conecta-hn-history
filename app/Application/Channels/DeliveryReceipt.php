<?php

declare(strict_types=1);

namespace App\Application\Channels;

final readonly class DeliveryReceipt
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $tenantId,
        public string $channel,
        public string $connectionKey,
        public string $idempotencyKey,
        public ?string $providerMessageId,
        public ?string $providerEventId,
        public string $deliveryState,
        public string $costAmount,
        public string $costCurrency,
        public string $providerIdempotencyKey,
        public array $metadata = [],
    ) {
        if (trim($providerIdempotencyKey) === '') {
            throw new \InvalidArgumentException('Delivery receipt requires a provider idempotency key.');
        }
    }

    public function withProviderIdempotencyKey(string $providerIdempotencyKey): self
    {
        return new self(
            tenantId: $this->tenantId,
            channel: $this->channel,
            connectionKey: $this->connectionKey,
            idempotencyKey: $this->idempotencyKey,
            providerMessageId: $this->providerMessageId,
            providerEventId: $this->providerEventId,
            deliveryState: $this->deliveryState,
            costAmount: $this->costAmount,
            costCurrency: $this->costCurrency,
            providerIdempotencyKey: $providerIdempotencyKey,
            metadata: $this->metadata,
        );
    }
}
