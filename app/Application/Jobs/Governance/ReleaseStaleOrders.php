<?php

declare(strict_types=1);

namespace App\Application\Jobs\Governance;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * ReleaseStaleOrders - Garbage Collector for Abandoned Checkouts
 *
 * Releases inventory locked by PENDING orders older than configured threshold.
 * Can run globally (all tenants) or scoped to a single tenant.
 */
final class ReleaseStaleOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    /**
     * @param int|null $tenantId If null, processes ALL tenants (global cleanup)
     */
    public function __construct(
        public readonly ?int $tenantId = null
    ) {}

    public function handle(): void
    {
        $query = Order::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30)); // 30 min threshold

        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        }

        $affected = $query->update([
            'status' => 'cancelled',
            'cancelled_reason' => 'stale_order',
        ]);

        Log::info("ReleaseStaleOrders: Cancelled {$affected} stale orders", [
            'tenant_id' => $this->tenantId ?? 'all',
            'threshold' => '30 minutes',
        ]);
    }

    public function tags(): array
    {
        $tags = ['governance', 'orders', 'cleanup'];
        
        if ($this->tenantId !== null) {
            $tags[] = 'tenant:' . $this->tenantId;
        }
        
        return $tags;
    }
}
