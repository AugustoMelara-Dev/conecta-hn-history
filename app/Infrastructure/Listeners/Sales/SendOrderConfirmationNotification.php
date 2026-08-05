<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Events\OrderCreated;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Order; // Added for re-fetching
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
        return [app(TenantQueueMiddleware::class)];
    }

    // Temporary property to hold tenant ID for middleware access
    public string $tenantId;

    public function handle(OrderCreated $event): void
    {
        $this->tenantId = $event->tenantId()->toString();

        $order = Order::query()
            // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->with('tenant.user')
            ->find($event->orderId);

        if (! $order) {
            Log::error('AsyncWorker: Order not found', ['order_id' => $event->orderId]);

            return;
        }

        Log::info('AsyncWorker: Sending NewOrderNotification', ['order_id' => $order->id]);

        try {
            if ($order->tenant && $order->tenant->user) {
                $order->tenant->user->notify(new NewOrderNotification($order));
            } else {
                Log::warning('AsyncWorker: Cannot notify, tenant owner missing', ['order_id' => $order->id]);
            }
        } catch (\Exception $e) {
            Log::error('AsyncWorker: Notification failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // We do not rethrow to avoid infinite loops unless it's a transient failure we want to retry.
            // For notifications, fail-silent is often preferred after logging.
        }
    }
}
