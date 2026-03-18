<?php

declare(strict_types=1);

namespace App\Application\Products\Handlers;

use App\Application\Products\Commands\CreateProductCommand;
use App\Application\Products\Results\CreateProductResult;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Product\Factories\ProductFactory;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;

/**
 * CreateProductHandler - Command Handler for Product Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TYPE SAFETY ENFORCED: No Array Persistence                             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Uses ProductFactory for atomic creation.                             ║
 * ║  - Uses Repository->save(ProductEntity).                                ║
 * ║  - Transactional via TransactionManagerInterface.                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CreateProductHandler
{
    use LogSanitizer;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductFactory $productFactory,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    public function handle(CreateProductCommand $command): CreateProductResult
    {
        return $this->transactionManager->transaction(function () use ($command) {
            return $this->processCreation($command);
        });
    }

    private function processCreation(CreateProductCommand $command): CreateProductResult
    {
        $productEntity = $this->productFactory->createNew(
            tenantId: $command->tenantId,
            name: $command->name,
            description: $command->description,
            price: (int) $command->price, // Passing raw value. Factory expects int.
            stock: $command->stock,
            sku: $command->sku,
            categoryId: $command->categoryId,
            imagePath: $command->imagePath,
            isVisible: $command->isVisible
        );

        // 2. Persist Entity (Type Safe)
        $persistedProduct = $this->productRepository->save($productEntity);
        $productId = $persistedProduct->id
            ?? throw new \LogicException('Product repository must return a persisted product ID.');

        // 3. Dispatch Domain Event
        $this->eventDispatcher->dispatch(new \App\Domain\Product\Events\ProductCreated(
            productId: $productId,
            tenantId: $command->tenantId,
            name: $command->name,
        ));

        // 4. Audit Log
        $this->logger->info('Product created successfully', $this->sanitizePayload([
            'product_id' => $productId,
            'tenant_id' => $command->tenantId,
            'sku' => $persistedProduct->sku,
        ], $this->scrubber));

        return CreateProductResult::fromEntity($persistedProduct);
    }
}
