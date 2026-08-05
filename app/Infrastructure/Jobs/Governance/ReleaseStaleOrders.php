<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Governance;

use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\TransitionOrderCommand;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cancels abandoned pending orders for exactly one tenant.
 *
 * The global scheduler delegates fan-out to DispatchStaleOrderCleanup. This
 * worker restores tenant context and still applies an explicit tenant filter.
 * Each cancellation flows through the canonical order state machine so audit
 * history and OrderCancelled side effects remain connected.
 */
final class ReleaseStaleOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly string $tenantId) {}

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function handle(CanonicalOrderWriter $writer): void
    {
        $threshold = now()->subMinutes(30);
        $cancelled = 0;

        Order::query()
            ->where('tenant_id', $this->tenantId)
            ->where('status', Order::STATUS_PENDING)
            ->where('created_at', '<', $threshold)
            ->select('id')
            ->chunkById(100, function ($orders) use ($writer, $threshold, &$cancelled): void {
                foreach ($orders as $candidate) {
                    $lockKey = "stale-order:{$this->tenantId}:{$candidate->getKey()}";

                    Cache::lock($lockKey, 60)->get(function () use ($candidate, $writer, $threshold, &$cancelled): void {
                        $order = Order::query()
                            ->where('tenant_id', $this->tenantId)
                            ->whereKey($candidate->getKey())
                            ->first();

                        if (
                            $order === null
                            || $order->status !== Order::STATUS_PENDING
                            || $order->created_at === null
                            || ! $order->created_at->lt($threshold)
                        ) {
                            return;
                        }

                        $writer->cancel(new TransitionOrderCommand(
                            tenantId: $this->tenantId,
                            actorId: null,
                            orderId: (int) $order->getKey(),
                            targetStatus: Order::STATUS_CANCELLED,
                            reason: 'stale_order',
                            systemInitiated: true,
                        ));
                        $cancelled++;
                    });
                }
            });

        Log::info("ReleaseStaleOrders: Cancelled {$cancelled} stale orders", [
            'tenant_id' => $this->tenantId,
            'threshold' => '30 minutes',
        ]);
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['governance', 'orders', 'cleanup', 'tenant:'.$this->tenantId];
    }
}
