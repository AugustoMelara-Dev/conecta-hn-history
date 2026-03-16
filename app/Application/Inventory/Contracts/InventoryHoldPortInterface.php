<?php

declare(strict_types=1);

namespace App\Application\Inventory\Contracts;

interface InventoryHoldPortInterface
{
    public function reserve(
        string $tenantId,
        string $locationId,
        string $sessionId,
        int $productId,
        float $quantity,
        int $expiresInMinutes = 15
    ): void;

    public function commit(string $tenantId, string $sessionId, string|int|null $referenceId = null): void;

    public function releaseExpired(): int;
}
