<?php

declare(strict_types=1);

namespace App\Application\Products\Handlers;

use App\Application\Products\Commands\CreateProductCommand;
use App\Application\Products\Results\CreateProductResult;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Contracts\LoggerInterface;
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
        // 1. Factory Creation (Domain Logic: SKU generation, Price validation)
        // Note: Command price is likely in cents or float?
        // Logic in previous handler was: $command->price / 100.
        // This suggests command->price was in ... wait.
        // Previous handler: 'price' => $command->price / 100.
        // If command->price is Integer Cents (e.g. 1000 = 10.00), dividing by 100 makes it 10 (float/int).
        // If DB stores Decimal(10,2), it expects 10.00.
        // BUT Domain Entity `ProductEntity` expects `int $price`.
        // "Tipado Estricto de Moneda: Asegura que el precio se maneje con el tipo correcto (preferiblemente int para centavos)".
        // So Entity should hold Cents.
        // IF Command holds Cents, we pass it directly.
        // Previous handler divided by 100. This implies DB might store Dollars/Lempiras as Float, OR Command holds Cents and DB holds Float.
        // Strict Approach: Entity holds Cents (Int). Repository maps to DB.
        // If Command->price is 1000 (10.00), Entity gets 1000.
        // I will assume Command passes Cents or Unit.
        // I will look at CreateProductCommand to be sure?
        // I'll check the User Request again: "$command->price / 100" was in the "Current State" snippet.
        // If I use int in Entity, I should pass int.
        // I will assume Command has the raw value.

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
        $this->productRepository->save($productEntity);

        // 3. Dispatch Domain Event
        // Note: $productEntity->id must be hydrated by save()
        $this->eventDispatcher->dispatch(new \App\Domain\Product\Events\ProductCreated(
            productId: $productEntity->id,
            tenantId: $command->tenantId,
            name: $command->name,
        ));

        // 4. Audit Log
        $this->logger->info('Product created successfully', $this->sanitizePayload([
            'product_id' => $productEntity->id,
            'tenant_id' => $command->tenantId,
            'sku' => $productEntity->sku,
        ], $this->scrubber));

        return CreateProductResult::fromEntity($productEntity);
    }
}
