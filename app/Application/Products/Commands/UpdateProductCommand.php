<?php

declare(strict_types=1);

namespace App\Application\Products\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;

/**
 * UpdateProductCommand - Immutable Input Contract for Product Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY: Tenant ID required for ownership verification (IDOR guard).  ║
 * ║  SERIALIZATION SAFE: No UploadedFile, only path reference.              ║
 * ║  IDEMPOTENT: Network retries must not cause inconsistent updates.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateProductCommand implements IdempotentCommandInterface
{
    public function __construct(
        /** Tenant ID for ownership verification */
        public string $tenantId,

        /** Product ID to update */
        public int $productId,

        /** Product name */
        public string $name,

        /** Product description */
        public string $description,

        /** Price in cents (integer for precision) */
        public int $price,

        /** Idempotency Key (mandatory for deduplication) */
        public string $idempotencyKey,

        /** Stock quantity */
        public int $stock = 0,

        /** Category ID */
        public ?int $categoryId = null,

        /**
         * New image path (if updating image).
         * NULL means keep existing image.
         */
        public ?string $imagePath = null,

        /** Product visibility */
        public bool $isVisible = true,

        /** Schema version for upcasting support */
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
