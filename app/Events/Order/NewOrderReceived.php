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
 * NewOrderReceived - Tenant-Aware Order Event with Secure Broadcast
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DUAL-PURPOSE: Backend Trigger + Real-Time Frontend Notification        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  BACKEND (Queued Listeners):                                            ║
 * ║    - SendOrderConfirmationEmail                                         ║
 * ║    - NotifyTenantOwnerViaSms                                            ║
 * ║    - SyncToExternalERPSystem                                            ║
 * ║                                                                          ║
 * ║  FRONTEND (WebSocket):                                                  ║
 * ║    - Dashboard "New Order!" notification                                ║
 * ║    - Kitchen display alert                                              ║
 * ║                                                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  TENANT CONTEXT PRESERVATION                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM: Queue Workers lose tenant context during deserialization      ║
 * ║    1. Event dispatched with Order in Tenant A environment               ║
 * ║    2. Serialized to Redis/SQS                                          ║
 * ║    3. Worker deserializes Order, but app is in "global" context         ║
 * ║    4. Mail config, filesystem, etc. point to wrong tenant!             ║
 * ║                                                                          ║
 * ║  SOLUTION: Explicit $tenantId property enables Queue Middleware to      ║
 * ║    restore tenant context BEFORE listener execution.                   ║
 * ║                                                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  @warning DO NOT ADD SENSITIVE CUSTOMER PII TO BROADCAST PAYLOAD!       ║
 * ║           Email, phone, address MUST NOT appear in broadcastWith().    ║
 * ║           Full details should be fetched via authenticated API.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * CHANNEL: private-tenant.{tenant_id}
 * BROADCAST NAME: order.new
 */
class NewOrderReceived implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Tenant ID for Queue Middleware context restoration.
     * This is NOT redundant with $order->tenant_id because the Order
     * might not be loaded when the Queue Worker inspects the payload.
     */
    public readonly int $tenantId;

    public function __construct(
        public readonly Order $order
    ) {
        // Capture tenant context at dispatch time
        $this->tenantId = $order->tenant_id;
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
     * @warning SECURITY: Only expose essential UI data.
     *          NO customer email, phone, address, or payment tokens!
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number ?? 'ORD-' . $this->order->id,
            'amount_cents' => $this->order->total ?? 0,
            'amount_formatted' => 'L ' . number_format(($this->order->total ?? 0) / 100, 2),
            'item_count' => $this->order->items_count ?? 0,
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
