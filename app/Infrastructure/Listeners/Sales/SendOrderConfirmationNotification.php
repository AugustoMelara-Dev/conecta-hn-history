<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Events\OrderCreated;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Notifications\NewOrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * SendOrderConfirmationNotification
 * 
 * ASYNC WORKER: Handles the side-effect of notifying the user about a new order.
 * Decoupled from the critical transaction path.
 */
final class SendOrderConfirmationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        // Ensure we hydrate the specific tenant context for this notification
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    // Temporary property to hold tenant ID for middleware access
    public int $tenantId;

    public function handle(OrderCreated $event): void
    {
        $this->tenantId = $event->tenantId();
        $order = $event->order;

        Log::info("AsyncWorker: Sending NewOrderNotification", ['order_id' => $order->id]);

        try {
            // Re-hydrate relationships if needed specially for the notification
            // But since OrderCreated is ShouldDispatchAfterCommit, models *should* be available.
            // Defensive coding:
            if ($order->tenant && $order->tenant->user) {
                $order->tenant->user->notify(new NewOrderNotification($order));
            } else {
                Log::warning("AsyncWorker: Cannot notify, tenant owner missing", ['order_id' => $order->id]);
            }
        } catch (\Exception $e) {
            Log::error("AsyncWorker: Notification failed", [
                'order_id' => $order->id, 
                'error' => $e->getMessage()
            ]);
            // We do not rethrow to avoid infinite loops unless it's a transient failure we want to retry.
            // For notifications, fail-silent is often preferred after logging.
        }
    }
}
