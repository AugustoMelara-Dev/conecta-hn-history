<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

final readonly class HoldCartInventoryCommand
{
    /**
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function __construct(
        public string|int $tenantId,
        public string $sessionId,
        public array $items,
        public int $expiresInMinutes = 15
    ) {}
}
