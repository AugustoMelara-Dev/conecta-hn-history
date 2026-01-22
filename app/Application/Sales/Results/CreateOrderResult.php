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
 * ║  IDEMPOTENCY READY: Implements strict serialization for caching.        ║
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
        /** @var array<int, OrderItemData|array> */
        public array $items = [],
    ) {}

    /**
     * Serializes the object to a scalar array for JSON storage.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->orderNumber,
            'totalAmount' => $this->totalAmount,
            'status' => $this->status,
            'itemsCount' => $this->itemsCount,
            'currency' => $this->currency,
            // We serialize items as pure arrays if they are objects
            'items' => array_map(function ($item) {
                return $item instanceof OrderItemData ? (array) $item : $item;
            }, $this->items),
        ];
    }

    /**
     * Factory method to hydrate from scalar array (Cache/JSON).
     */
    public static function fromArray(array $data): self
    {
        // Reconstruct OrderItemData objects if possible, or keep as array
        // For simple idempotency return, keeping as array is often sufficient for API response
        $items = array_map(function ($item) {
            // Assuming OrderItemData could be reconstructed if needed, but for now array is safe
            // return is_array($item) ? OrderItemData::fromArray($item) : $item;
            return $item; // Keep as array for now to avoid dependency depth issues
        }, $data['items'] ?? []);

        return new self(
            id: (int) $data['id'],
            orderNumber: (string) $data['orderNumber'],
            totalAmount: (float) $data['totalAmount'],
            status: (string) $data['status'],
            itemsCount: (int) $data['itemsCount'],
            currency: (string) $data['currency'],
            items: $items
        );
    }

    /**
     * Factory method to hydrate from Domain Entity / Eloquent Model.
     *
     * @param  object  $order  The Order entity (Model or Domain Entity)
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
            // Ideally map items here too
            items: []
        );
    }
}
