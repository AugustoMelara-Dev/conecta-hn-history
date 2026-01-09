<?php

declare(strict_types=1);

namespace App\Application\Products\Handlers;

use App\Application\Products\Commands\UpdateProductCommand;
use App\Application\Products\Results\UpdateProductResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

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
final class UpdateProductHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Handle product update command.
     *
     * @param UpdateProductCommand $command
     * @return UpdateProductResult
     * @throws ModelNotFoundException If product not found or tenant mismatch
     */
    public function handle(UpdateProductCommand $command): UpdateProductResult
    {
        // 1. Load Product from Repository
        $product = $this->productRepository->findById($command->productId);

        // 2. Guard: Product Existence
        if ($product === null) {
            throw new ModelNotFoundException("Product not found: {$command->productId}");
        }

        // 3. SECURITY GUARD: Tenant Ownership Verification (IDOR Protection)
        // We hide the existence of the resource to prevent enumeration attacks
        if ($product->tenant_id !== $command->tenantId) {
            Log::warning('SECURITY: Cross-tenant product access attempt', [
                'requested_product_id' => $command->productId,
                'requesting_tenant_id' => $command->tenantId,
                'actual_tenant_id' => $product->tenant_id,
            ]);
            throw new ModelNotFoundException("Product not found: {$command->productId}");
        }

        // 4. Update Product Properties
        $product->sku = $command->sku; // Added SKU update
        $product->name = $command->name;
        $product->description = $command->description;
        $product->price = $command->price;
        $product->stock = $command->stock;
        $product->category_id = $command->categoryId;
        $product->is_visible = $command->isVisible;
        
        // Only update image if a new one was provided
        if ($command->imagePath !== null) {
            $product->image = $command->imagePath;
        }

        // 5. Capture dirty attributes BEFORE save
        $changedAttributes = method_exists($product, 'getDirty') 
            ? array_keys($product->getDirty()) 
            : [];

        // 6. Persist via Repository
        $this->productRepository->save($product);

        // 7. Dispatch Domain Event (Transactional Outbox)
        $this->eventDispatcher->dispatch(new \App\Domain\Product\Events\ProductUpdated(
            productId: $product->id,
            tenantId: $command->tenantId,
            name: $command->name,
        ));

        // 8. Audit Log (PII Scrubbed)
        Log::info('Product updated successfully', $this->sanitizePayload([
            'product_id' => $product->id,
            'tenant_id' => $command->tenantId,
            'changed_fields' => $changedAttributes,
        ]));

        return UpdateProductResult::fromEntity($product, $changedAttributes);
    }
}

