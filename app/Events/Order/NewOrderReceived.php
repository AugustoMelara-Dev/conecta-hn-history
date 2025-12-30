<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Models\Order;
use App\Traits\TenantAwareEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NewOrderReceived - Queue-Safe, Tenant-Aware Order Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MULTI-TENANT QUEUE SAFETY                                              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  @notice The $tenantId property is CRITICAL for queue processing.       ║
 * ║          It MUST be set in constructor as a PRIMITIVE (int), not model. ║
 * ║                                                                          ║
 * ║  WHY: SerializesModels stores the Order as {"order_id": 5}.             ║
 * ║       When Queue Worker deserializes, it calls Order::find(5).          ║
 * ║       BUT: Worker doesn't know which tenant database to use!            ║
 * ║                                                                          ║
 * ║  SOLUTION FLOW:                                                         ║
 * ║    1. Event is dispatched with $tenantId = $order->tenant_id            ║
 * ║    2. Serialized to queue: {"tenantId": 3, "order": {"id": 5}}          ║
 * ║    3. TenantQueueMiddleware reads tenantId from payload                 ║
 * ║    4. Middleware switches DB context to Tenant 3                        ║
 * ║    5. NOW SerializesModels can safely deserialize Order 5               ║
 * ║                                                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  BROADCAST SECURITY                                                     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  @warning DO NOT EXPOSE PII IN WEBSOCKET BROADCAST!                     ║
 * ║           broadcastWith() returns ONLY safe UI data.                    ║
 * ║           NO: customer_email, customer_phone, shipping_address          ║
 * ║           Full order details must be fetched via authenticated API.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * CHANNEL: private-tenant.{tenant_id}
 * BROADCAST NAME: order.new
 */
class NewOrderReceived implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels, TenantAwareEvent;

    public function __construct(
        public readonly Order $order
    ) {
        // CRITICAL: Capture tenant context BEFORE serialization
        // This primitive value is readable by Queue Middleware
        $this->setTenantContext($order->tenant_id);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenantId),
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
     * @warning SECURITY: Only expose essential UI notification data.
     *          NO customer email, phone, address, or payment details!
     *          Client must fetch full order via authenticated /api/orders/{id}.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number ?? 'ORD-' . $this->order->id,
            'amount_cents' => $this->order->total ?? 0,
            'amount_formatted' => 'L ' . number_format(($this->order->total ?? 0) / 100, 2),
            'item_count' => $this->order->items_count ?? 0,
            'customer_name' => $this->order->customer_name ?? 'Cliente', // Safe: just name
            'created_at_human' => $this->order->created_at?->diffForHumans() ?? 'ahora',
            'tenant_id' => $this->tenantId,
        ];
    }

    /**
     * Determine if this event should be broadcast.
     */
    public function broadcastWhen(): bool
    {
        return $this->order->exists && $this->order->id !== null;
    }
}
