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
 * OrderStatusUpdated - Real-Time Status Change Notification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CHANNELS:                                                              ║
 * ║    - private-tenant.{tenant_id}  → Tenant dashboard                     ║
 * ║    - private-order.{uuid}        → Customer tracking screen             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Broadcasts when an order transitions to a new status.
 * Enables real-time tracking for both operators and customers.
 */
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Order $order,
        public readonly string $previousStatus,
        public readonly string $newStatus
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * DUAL-CHANNEL: Both tenant operators and customers receive updates.
     */
    public function broadcastOn(): array
    {
        $channels = [
            // Tenant dashboard channel
            new PrivateChannel('tenant.' . $this->order->tenant_id),
        ];

        // Customer tracking channel (using order UUID for security)
        if ($this->order->tracking_uuid) {
            $channels[] = new PrivateChannel('order.' . $this->order->tracking_uuid);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.status-updated';
    }

    /**
     * Get the data to broadcast.
     *
     * OPTIMIZATION: Minimal payload for fast WebSocket transmission.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'updated_at' => now()->toIso8601String(),
            
            // Status metadata for UI
            'status_label' => $this->getStatusLabel($this->newStatus),
            'is_terminal' => in_array($this->newStatus, [
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
            ]),
        ];
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => 'Pendiente',
            Order::STATUS_CONFIRMED => 'Confirmado',
            Order::STATUS_PROCESSING => 'En Preparación',
            Order::STATUS_READY => 'Listo',
            Order::STATUS_DELIVERED => 'Entregado',
            Order::STATUS_CANCELLED => 'Cancelado',
            default => ucfirst($status),
        };
    }
}
