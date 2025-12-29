<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NewOrderReceived - Real-Time Tenant Dashboard Notification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CHANNEL: private-tenant.{tenant_id}                                    ║
 * ║  AUDIENCE: Tenant dashboard (kitchen/admin)                             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Broadcasts immediately when a new order is placed.
 * Enables zero-latency kitchen notifications.
 */
class NewOrderReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Order $order
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * SECURITY: Private channel ensures only authenticated tenant users receive.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->order->tenant_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.new';
    }

    /**
     * Get the data to broadcast.
     *
     * OPTIMIZATION: Lightweight DTO - no heavy relationships serialized.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'total' => (float) $this->order->total,
            'item_count' => $this->order->items_count ?? $this->order->items()->count(),
            'customer_name' => $this->order->customer_name,
            'customer_phone' => $this->order->customer_phone,
            'payment_method' => $this->order->payment_method?->value ?? $this->order->payment_method,
            'created_at' => $this->order->created_at->toIso8601String(),
        ];
    }
}
