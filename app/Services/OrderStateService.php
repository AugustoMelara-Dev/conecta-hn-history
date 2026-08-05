<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\TransitionOrderCommand;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;

/**
 * @deprecated Dispatch TransitionOrderCommand to CanonicalOrderWriter directly.
 */
final readonly class OrderStateService
{
    private const TRANSITION_MAP = [
        Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
        Order::STATUS_CONFIRMED => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
        Order::STATUS_PROCESSING => [Order::STATUS_READY, Order::STATUS_CANCELLED],
        Order::STATUS_READY => [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function __construct(private CanonicalOrderWriter $writer) {}

    public function transition(Order $order, string $newStatus): Order
    {
        $actorId = auth()->id();
        $command = new TransitionOrderCommand(
            tenantId: (string) $order->tenant_id,
            actorId: $actorId === null ? null : (string) $actorId,
            orderId: (int) $order->getKey(),
            targetStatus: $newStatus,
            reason: $newStatus === Order::STATUS_CANCELLED
                ? (string) ($order->cancellation_reason ?: 'status_transition')
                : null,
        );

        $result = $newStatus === Order::STATUS_CANCELLED
            ? $this->writer->cancel($command)
            : $this->writer->transition($command);

        return $result->order;
    }

    public function validateTransition(string $from, string $to): void
    {
        $allowed = $this->getAllowedTransitions($from);
        if (! in_array($to, $allowed, true)) {
            throw new InvalidOrderTransitionException($from, $to, $allowed);
        }
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->getAllowedTransitions($from), true);
    }

    /** @return array<int, string> */
    public function getAllowedTransitions(string $status): array
    {
        return self::TRANSITION_MAP[$status] ?? [];
    }
}
