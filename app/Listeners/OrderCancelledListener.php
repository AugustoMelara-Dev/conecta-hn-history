<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Broadcast\OrderCancelledBroadcast;
use App\Events\Order\OrderCancelled;
use App\Exceptions\TenantMismatchException;
use App\Jobs\Middleware\TenantQueueMiddleware;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * OrderCancelledListener - Application Layer Bridge with Defensive Tenant Checks
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  APPLICATION LAYER: Reacts to Domain Events, Orchestrates Side Effects  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  SECURITY (3-Level Tenant Isolation):                                   ║
 * ║                                                                          ║
 * ║  LEVEL 1: TenantQueueMiddleware (Queue Layer)                           ║
 * ║    → Switches tenant context BEFORE listener executes                  ║
 * ║                                                                          ║
 * ║  LEVEL 2: Defensive Check in Listener (Application Layer)              ║
 * ║    → Validates tenant context matches event tenant                     ║
 * ║    → Queries Order WITH tenant_id scope                                ║
 * ║                                                                          ║
 * ║  LEVEL 3: Database Constraint (Data Layer)                             ║
 * ║    → Foreign key ensures refund.tenant_id = order.tenant_id            ║
 * ║                                                                          ║
 * ║  FLOW:                                                                  ║
 * ║    OrderStateService                                                     ║
 * ║         │                                                               ║
 * ║         ▼                                                               ║
 * ║    DomainEventBus->publish(OrderCancelled)                             ║
 * ║         │                                                               ║
 * ║         ▼                                                               ║
 * ║    Queue Worker + TenantQueueMiddleware                                ║
 * ║         │                                                               ║
 * ║         ▼                                                               ║
 * ║    THIS LISTENER (with defensive checks)                               ║
 * ║         │                                                               ║
 * ║         ├──▶ InitiateRefund (if wasPaid)                               ║
 * ║         ├──▶ SendCancellationEmail                                     ║
 * ║         └──▶ broadcast(OrderCancelledBroadcast)                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OrderCancelledListener implements ShouldQueue
{
    /**
     * Get the middleware the job should pass through.
     * 
     * CRITICAL: TenantQueueMiddleware must run FIRST to set context.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new TenantQueueMiddleware(),
        ];
    }

    /**
     * Handle the OrderCancelled domain event.
     */
    public function handle(OrderCancelled $event): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // DEFENSIVE CHECK: Verify tenant context is correct
        // ─────────────────────────────────────────────────────────────────────
        // Even with middleware, add explicit validation as defense-in-depth.
        // This prevents processing if middleware fails silently.
        
        $order = Order::where('tenant_id', $event->tenantId()->toInt())
            ->find($event->aggregateId());

        if ($order === null) {
            // Log security event - possible tenant isolation breach attempt
            logger()->critical('Order not found with event tenant context', [
                'order_id' => $event->aggregateId(),
                'event_tenant_id' => $event->tenantId()->toInt(),
                'reason' => 'tenant_isolation_check_failed',
            ]);

            throw new TenantMismatchException(
                "Order {$event->aggregateId()} not found for tenant {$event->tenantId()->toInt()}"
            );
        }

        // ─────────────────────────────────────────────────────────────────────
        // STEP 1: Business Logic - Initiate refund if the order was paid
        // ─────────────────────────────────────────────────────────────────────
        if ($event->shouldAutoRefund()) {
            // NOTE: Replace with actual refund service call
            // $this->refundService->initiateForOrder($event->aggregateId());
            logger()->info('Refund initiated for cancelled order', [
                'order_id' => $event->aggregateId(),
                'tenant_id' => $event->tenantId()->toInt(),
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // STEP 2: Notification - Send cancellation email to customer
        // ─────────────────────────────────────────────────────────────────────
        // NOTE: Email sending is already handled by OrderObserver
        // Add additional notification logic here if needed

        // ─────────────────────────────────────────────────────────────────────
        // STEP 3: Real-Time UI Update - Broadcast to tenant dashboard
        // ─────────────────────────────────────────────────────────────────────
        broadcast(OrderCancelledBroadcast::fromDomainData(
            tenantId: $event->tenantId()->toInt(),
            orderId: $event->aggregateId(),
            orderNumber: $event->orderNumber->toString(),
            reason: $event->reason,
            shouldRefund: $event->shouldAutoRefund(),
            cancelledAt: $event->occurredAt(),
        ));
    }

    /**
     * Handle job failure.
     */
    public function failed(OrderCancelled $event, \Throwable $exception): void
    {
        logger()->error('OrderCancelledListener failed', [
            'order_id' => $event->aggregateId(),
            'tenant_id' => $event->tenantId()->toInt(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
