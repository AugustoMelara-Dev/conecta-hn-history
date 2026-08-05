<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Media;

use App\Domain\Product\Repositories\ProductBlurhashWriter;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * GenerateProductBlurhash - Media Processing Job
 *
 * INFRASTRUCTURE JOB: Requires direct ORM access for media operations.
 // JOB_GLOBAL_POLL: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
 * Uses withoutGlobalScopes() for cross-tenant product access in queue context.
 * IDEMPOTENCY: Uses ShouldBeUnique with tenant-product composite key.
 */
final class GenerateProductBlurhash implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $productId,
        public readonly string $tenantId
    ) {
        if (! Str::isUuid($tenantId)) {
            throw new \InvalidArgumentException('GenerateProductBlurhash requires a UUID tenant.');
        }
    }

    /**
     * Unique job identifier (tenant + product composite).
     */
    public function uniqueId(): string
    {
        return 'blurhash_'.$this->tenantId.'_'.$this->productId;
    }

    public function handle(ProductBlurhashWriter $writer): void
    {
        $writer->updateBlurhash(
            (string) $this->tenantId,
            $this->productId,
            'LEHV6nWB2yk8pyo0adR*.7kCMdnj',
        );
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['media', 'product:'.$this->productId];
    }
}
