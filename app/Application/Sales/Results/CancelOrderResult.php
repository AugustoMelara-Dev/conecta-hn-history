<?php

declare(strict_types=1);

namespace App\Application\Sales\Results;

/**
 * CancelOrderResult - Immutable Output Contract for Order Cancellation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CQRS COMPLIANCE: Explicit return contract from CancelOrderHandler       ║
 * ║  TRANSPARENCY: Client knows the final state of the cancelled order       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CancelOrderResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $newStatus,
        public string $cancelledAt,
        public ?string $reason = null,
    ) {}

    /**
     * Factory method to hydrate from Domain Entity.
     *
     * @param object $order The cancelled Order entity
     * @param string|null $reason Cancellation reason
     * @return self
     */
    public static function fromEntity(object $order, ?string $reason = null): self
    {
        return new self(
            orderId: $order->id,
            orderNumber: $order->order_number,
            newStatus: $order->status,
            cancelledAt: $order->updated_at->toIso8601String(),
            reason: $reason,
        );
    }
}
