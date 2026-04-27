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
        CheckoutSession::query()->withoutGlobalScopes()->updateOrCreate(
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

    public function markAsAbandoned(int $timeoutMinutes): array
    {
        $threshold = now()->subMinutes($timeoutMinutes);

        $abandoned = CheckoutSession::query()->withoutGlobalScopes()
            ->where('status', 'active')
            ->where('last_active_at', '<=', $threshold)
            ->get();

        if ($abandoned->isEmpty()) {
            return [];
        }

        CheckoutSession::query()->withoutGlobalScopes()
            ->whereIn('id', $abandoned->pluck('id'))
            ->update(['status' => 'abandoned']);

        return $abandoned->map(fn ($session) => [
            'id' => $session->id,
            'tenant_id' => (string) $session->tenant_id,
            'session_id' => $session->session_id,
            'user_id' => $session->user_id,
            'subtotal' => (float) $session->subtotal,
        ])->toArray();
    }

    public function markAsConverted(string|int $tenantId, string $sessionId): void
    {
        CheckoutSession::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->update(['status' => 'converted']);
    }
}
