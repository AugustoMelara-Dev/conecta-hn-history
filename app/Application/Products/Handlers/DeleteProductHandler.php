<?php

declare(strict_types=1);

namespace App\Application\Products\Handlers;

use App\Application\Products\Commands\DeleteProductCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Product\Events\ProductDeleted;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Application\Shared\Contracts\LoggerInterface;

/**
 * DeleteProductHandler - Command Handler for Product Soft Deletion
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SOFT DELETE: Preserves images for potential restoration.               ║
 * ║  IDOR PROTECTED: Verifies tenant ownership before deletion.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class DeleteProductHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle product deletion command.
     *
     * @throws ModelNotFoundException If product not found or tenant mismatch
     */
    public function handle(DeleteProductCommand $command): void
    {
        // 1. Load Product
        $product = $this->productRepository->findById($command->productId);

        if ($product === null) {
            throw new ModelNotFoundException("Product not found: {$command->productId}");
        }

        // 2. SECURITY GUARD: Tenant Ownership Verification
        if ($product->tenant_id !== $command->tenantId) {
            $this->logger->warning('SECURITY: Cross-tenant product deletion attempt', [
                'requested_product_id' => $command->productId,
                'requesting_tenant_id' => $command->tenantId,
            ]);
            throw new ModelNotFoundException("Product not found: {$command->productId}");
        }

        // 3. Soft Delete via Repository
        $this->productRepository->delete($product);

        // 4. Dispatch Domain Event
        $this->eventDispatcher->dispatch(new ProductDeleted(
            productId: $product->id,
            tenantId: $command->tenantId,
            name: $product->name,
        ));

        $this->logger->info('Product deleted', $this->sanitizePayload([
            'product_id' => $command->productId,
            'tenant_id' => $command->tenantId,
        ]));
    }
}