<?php

declare(strict_types=1);

namespace App\Application\Products\Handlers;

use App\Application\Products\Commands\ToggleProductVisibilityCommand;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Product\Exceptions\ProductNotFoundException;

/**
 * ToggleProductVisibilityHandler - Command Handler for Product Visibility Toggle
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SIMPLE TOGGLE: Inverts current is_visible state.                       ║
 * ║  IDOR PROTECTED: Verifies tenant ownership before modification.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ToggleProductVisibilityHandler
{
    use LogSanitizer;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle visibility toggle command.
     *
     * @throws ProductNotFoundException If product not found or tenant mismatch
     */
    public function handle(ToggleProductVisibilityCommand $command): void
    {
        // 1. Load Product
        $product = $this->productRepository->findById($command->productId);

        if ($product === null) {
            throw new ProductNotFoundException("Product not found: {$command->productId}");
        }

        // 2. SECURITY GUARD: Tenant Ownership Verification
        if ($product->tenant_id !== $command->tenantId) {
            $this->logger->warning('SECURITY: Cross-tenant product visibility toggle attempt', [
                'requested_product_id' => $command->productId,
                'requesting_tenant_id' => $command->tenantId,
            ]);
            throw new ProductNotFoundException("Product not found: {$command->productId}");
        }

        // 3. Toggle visibility
        $product->is_visible = ! $product->is_visible;

        // 4. Persist via Repository
        $this->productRepository->save($product);

        $this->logger->info('Product visibility toggled', $this->sanitizePayload([
            'product_id' => $command->productId,
            'tenant_id' => $command->tenantId,
            'is_visible' => $product->is_visible,
        ], $this->scrubber));
    }
}
