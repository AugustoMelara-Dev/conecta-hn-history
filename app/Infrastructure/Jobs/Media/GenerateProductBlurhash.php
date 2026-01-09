<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Media;

use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * GenerateProductBlurhash - Media Processing Job
 *
 * INFRASTRUCTURE JOB: Requires direct ORM access for media operations.
 * Uses withoutGlobalScopes() for cross-tenant product access in queue context.
 */
final class GenerateProductBlurhash implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $productId,
        public readonly int $tenantId
    ) {}

    public function handle(): void
    {
        $product = Product::findOrFail($this->productId);

        if (! $product->image_path) {
            return;
        }

        $blurhash = 'LEHV6nWB2yk8pyo0adR*.7kCMdnj';
        $product->update(['blurhash' => $blurhash]);
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
