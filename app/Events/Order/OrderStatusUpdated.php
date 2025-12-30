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
 * OrderStatusUpdated - Transaction-Safe Status Change Event
 *
 * Dispatched when an order transitions between statuses:
 * pending → confirmed → preparing → ready → delivered
 *
 * Implements ShouldDispatchAfterCommit to prevent race conditions.
 */
class OrderStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $previousStatus,
        public readonly string $newStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->order->tenant_id),
            new PrivateChannel('order.' . $this->order->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'updated_at' => $this->order->updated_at->toIso8601String(),
        ];
    }
}
