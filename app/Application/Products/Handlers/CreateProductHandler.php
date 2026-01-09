<?php

declare(strict_types=1);

namespace App\Application\Products\Handlers;

use App\Application\Products\Commands\CreateProductCommand;
use App\Application\Products\Results\CreateProductResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * CreateProductHandler - Command Handler for Product Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal + Transactional Outbox       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO DB::transaction (handled by Bus Middleware)                        ║
 * ║  - NO App\Models imports (uses Repository interfaces only)              ║
 * ║  - Multi-tenant isolation via tenant_id in product data                 ║
 * ║  - STRICT RETURN: CreateProductResult DTO (no object/array leak)        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class CreateProductHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Handle product creation command.
     *
     * @param CreateProductCommand $command
     * @return CreateProductResult
     */
    public function handle(CreateProductCommand $command): CreateProductResult
    {
        // 1. Create Product via Repository
        $product = $this->productRepository->create([
            'tenant_id' => $command->tenantId,
            'sku' => $command->sku,
            'name' => $command->name,
            'description' => $command->description,
            'price' => $command->price / 100, // Convert cents to decimal for storage
            'stock_quantity' => $command->stock,
            'category_id' => $command->categoryId,
            'image_path' => $command->imagePath,
            'is_visible' => $command->isVisible,
        ]);

        // 2. Dispatch Domain Event (Transactional Outbox)
        $this->eventDispatcher->dispatch(new \App\Domain\Product\Events\ProductCreated(
            productId: $product->id,
            tenantId: $command->tenantId,
            name: $command->name,
        ));

        // 3. Audit Log (PII Scrubbed)
        Log::info('Product created successfully', $this->sanitizePayload([
            'product_id' => $product->id,
            'tenant_id' => $command->tenantId,
            'name' => $command->name,
        ]));

        // 4. RETURN STRICT DTO (NO object leak)
        return CreateProductResult::fromEntity($product);
    }
}

