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
        public array $metadata = [],
    ) {}
}
