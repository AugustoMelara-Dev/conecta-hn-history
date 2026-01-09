<?php

declare(strict_types=1);

namespace App\Application\Sales\Results;

use App\Application\Sales\DTOs\OrderItemData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * CreateOrderResult - Immutable Output Contract for Order Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEMANTIC CONTRACT: Decouples API response from persistence model.      ║
 * ║  IDE FRIENDLY: Full autocompletion on consumer side.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
#[TypeScript]
final readonly class CreateOrderResult
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public float $totalAmount,
        public string $status,
        public int $itemsCount,
        public string $currency,
        /** @var array<int, OrderItemData> */
        public array $items = [],
    ) {}

    /**
     * Factory method to hydrate from Domain Entity / Eloquent Model.
     *
     * @param object $order The Order entity (Model or Domain Entity)
     * @return self
     */
    public static function fromEntity(object $order): self
    {
        return new self(
            id: $order->id,
            orderNumber: $order->order_number,
            totalAmount: (float) $order->total,
            status: $order->status,
            itemsCount: method_exists($order, 'items') ? $order->items()->count() : 0,
            currency: $order->currency ?? 'HNL',
        );
    }
}
