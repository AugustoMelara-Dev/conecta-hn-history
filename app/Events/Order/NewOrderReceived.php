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
 * NewOrderReceived - Transaction-Safe Order Event with Real-Time Broadcast
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DUAL-PURPOSE EVENT: Backend Trigger + Frontend Notification            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  BACKEND (Queued Listeners):                                            ║
 * ║    - SendOrderConfirmationEmail                                         ║
 * ║    - NotifyTenantOwnerViaSms                                            ║
 * ║    - UpdateInventoryReservations                                        ║
 * ║    - SyncToExternalERPSystem                                            ║
 * ║                                                                          ║
 * ║  FRONTEND (WebSocket Broadcast):                                        ║
 * ║    - Real-time dashboard notification                                   ║
 * ║    - Kitchen display "New Order!" alert                                 ║
 * ║    - Mobile app push via private channel                                ║
 * ║                                                                          ║
 * ║  ─────────────────────────────────────────────────────────────────────── ║
 * ║                                                                          ║
 * ║  TRANSACTION SAFETY (ShouldDispatchAfterCommit):                        ║
 * ║                                                                          ║
 * ║    PROBLEM without it:                                                  ║
 * ║      1. Controller saves order: $order->save();                         ║
 * ║      2. Event dispatched: event(new NewOrderReceived($order));          ║
 * ║      3. Queue Worker picks up job BEFORE transaction commits           ║
 * ║      4. Listener queries: Order::find($orderId) → NULL                  ║
 * ║      5. CRASH: ModelNotFoundException                                   ║
 * ║                                                                          ║
 * ║    SOLUTION:                                                            ║
 * ║      ShouldDispatchAfterCommit ensures the event is held in memory     ║
 * ║      until DB::commit() succeeds. Queue Worker only sees already-      ║
 * ║      committed data.                                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * CHANNEL: private-tenant.{tenant_id}
 * BROADCAST NAME: order.new
 */
class NewOrderReceived implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

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
            new PrivateChannel('tenant.' . $this->order->tenant_id),
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
     * SECURITY: Lightweight payload - no sensitive customer data over WebSockets.
     * Full order details should be fetched via authenticated API call.
     */
    public function broadcastWith(): array
    {
        $total = $this->order->total ?? 0;
        
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number ?? 'N/A',
            'status' => $this->order->status ?? 'pending',
            'total' => $total,
            'total_formatted' => 'L ' . number_format($total / 100, 2),
            'item_count' => $this->order->items_count ?? $this->order->relationLoaded('items') 
                ? $this->order->items->count() 
                : 0,
            'customer_name' => $this->order->customer_name ?? 'Cliente',
            'payment_method' => $this->order->payment_method?->value ?? $this->order->payment_method ?? 'cash',
            'created_at' => $this->order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /**
     * Determine if this event should be broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Only broadcast if order was successfully created
        return $this->order->exists && $this->order->id !== null;
    }
}
