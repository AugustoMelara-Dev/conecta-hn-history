<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

final readonly class SyncCheckoutSessionCommand
{
    public function __construct(
        public string|int $tenantId,
        public string $sessionId,
        public array $items,
        public float $subtotal,
        public string|int|null $userId = null
    ) {}
}
