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

    /**
     * @return array<int, array{id: int, tenant_id: string, session_id: string, user_id: string|int|null, subtotal: float}>
     */
    public function markAsAbandoned(int $timeoutMinutes): array;
}
