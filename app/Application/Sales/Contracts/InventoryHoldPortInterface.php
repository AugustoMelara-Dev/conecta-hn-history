<?php

declare(strict_types=1);

namespace App\Application\Sales\Contracts;

interface InventoryHoldPortInterface
{
    /**
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function createHolds(string|int $tenantId, string $sessionId, array $items, int $expiresInMinutes): void;

    public function releaseExpired(string|int $tenantId): int;
}
