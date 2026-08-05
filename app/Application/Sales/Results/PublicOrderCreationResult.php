<?php

declare(strict_types=1);

namespace App\Application\Sales\Results;

use App\Models\Order;

final readonly class PublicOrderCreationResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public float $total,
        public string $status,
        public ?string $whatsappUrl,
        public bool $replayed = false,
    ) {}

    public static function fromOrder(Order $order, bool $replayed = false): self
    {
        return new self(
            orderId: (int) $order->getKey(),
            orderNumber: (string) $order->order_number,
            total: (float) $order->total,
            status: (string) $order->status,
            whatsappUrl: $order->getWhatsAppUrl(),
            replayed: $replayed,
        );
    }

    /** @return array<string, int|float|string|bool|null> */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'total' => $this->total,
            'status' => $this->status,
            'whatsapp_url' => $this->whatsappUrl,
            'replayed' => $this->replayed,
        ];
    }
}
