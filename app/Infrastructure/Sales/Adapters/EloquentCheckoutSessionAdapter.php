<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales\Adapters;

use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Models\CheckoutSession;

class EloquentCheckoutSessionAdapter implements CheckoutSessionPortInterface
{
    public function upsertSession(
        string|int $tenantId,
        string $sessionId,
        array $items,
        float $subtotal,
        string|int|null $userId
    ): void {
        CheckoutSession::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'session_id' => $sessionId,
            ],
            [
                'user_id' => $userId,
                'items' => $items,
                'subtotal' => $subtotal,
                'last_active_at' => now(),
                'status' => 'active',
            ]
        );
    }
}
