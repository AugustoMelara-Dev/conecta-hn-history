<?php

declare(strict_types=1);

namespace App\Application\Sales\Contracts;

interface CheckoutSessionPortInterface
{
    public function upsertSession(
        string|int $tenantId,
        string $sessionId,
        array $items,
        float $subtotal,
        string|int|null $userId
    ): void;
}
