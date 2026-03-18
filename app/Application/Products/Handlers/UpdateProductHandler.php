<?php

declare(strict_types=1);

namespace App\Application\Products\Handlers;

use App\Application\Products\Commands\UpdateProductCommand;
use App\Application\Products\Results\UpdateProductResult;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Product\Exceptions\ProductNotFoundException;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;

/**
 * UpdateProductHandler - Command Handler for Product Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY HARDENED: Tenant ownership verification prevents IDOR.        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO DB::transaction (handled by Bus Middleware)                        ║
 * ║  - NO App\Models imports (uses Repository interfaces only)              ║
 * ║  - Guard clause prevents cross-tenant data modification                 ║
 * ║  - STRICT RETURN: UpdateProductResult DTO with dirty tracking           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateProductHandler
{
    use LogSanitizer;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle product update command.
     *
     * @throws ProductNotFoundException If product not found or tenant mismatch
     */
    public function handle(UpdateProductCommand $command): UpdateProductResult
    {
        // 1. Load Product from Repository
        $product = $this->productRepository->findById($command->productId);

        // 2. Guard: Product Existence
        if ($product === null) {
            throw ProductNotFoundException::withId($command->productId);
        }

        // 3. SECURITY GUARD: Tenant Ownership Verification (IDOR Protection)
        // We hide the existence of the resource to prevent enumeration attacks
        if (! $product->belongsToTenant($command->tenantId)) {
            $this->logger->warning('SECURITY: Cross-tenant product access attempt', [
                'requested_product_id' => $command->productId,
                'requesting_tenant_id' => $command->tenantId,
                'actual_tenant_id' => $product->tenantId,
            ]);
            throw ProductNotFoundException::withId($command->productId);
        }

        $updatedProduct = $product->withUpdatedDetails(
            sku: $command->sku,
            name: $command->name,
            description: $command->description,
            price: $command->price,
            stockQuantity: $command->stock,
            categoryId: $command->categoryId,
            imagePath: $command->imagePath ?? $product->imagePath,
            isVisible: $command->isVisible
        );
        $changedAttributes = $updatedProduct->changedAttributesComparedTo($product);

        // 6. Persist via Repository
        $persistedProduct = $this->productRepository->save($updatedProduct);
        $productId = $persistedProduct->id
            ?? throw new \LogicException('Product repository must return a persisted product ID.');

        // 7. Dispatch Domain Event (Transactional Outbox)
        $this->eventDispatcher->dispatch(new \App\Domain\Product\Events\ProductUpdated(
            productId: $productId,
            tenantId: $command->tenantId,
            name: $command->name,
        ));

        // 8. Audit Log (PII Scrubbed)
        $this->logger->info('Product updated successfully', $this->sanitizePayload([
            'product_id' => $productId,
            'tenant_id' => $command->tenantId,
            'changed_fields' => $changedAttributes,
        ], $this->scrubber));

        return UpdateProductResult::fromEntity($persistedProduct, $changedAttributes);
    }
}
