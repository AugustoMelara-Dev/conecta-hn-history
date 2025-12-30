<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OrderShipped - Semantic Event for Fulfillment Completion
 *
 * LISTENERS:
 *   - SendTrackingEmailListener
 *   - SendShipmentSmsListener
 *   - UpdateDeliveryEstimateListener
 *   - SyncToShippingCarrierListener
 */
class OrderShipped implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $tenantId;

    public function __construct(
        public readonly Order $order,
        public readonly string $trackingNumber,
        public readonly string $carrier,
        public readonly ?\DateTimeInterface $estimatedDelivery = null,
    ) {
        $this->tenantId = $order->tenant_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenantId),
            new PrivateChannel('order.' . $this->order->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.shipped';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'tracking_number' => $this->trackingNumber,
            'carrier' => $this->carrier,
            'estimated_delivery' => $this->estimatedDelivery?->format('Y-m-d'),
            'shipped_at' => now()->toIso8601String(),
            'tenant_id' => $this->tenantId,
        ];
    }
}
