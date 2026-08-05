<?php

declare(strict_types=1);

namespace App\Application\Products\Commands;

/**
 * CreateProductCommand - Immutable Input Contract for Product Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SERIALIZATION SAFE: No UploadedFile, only path reference.              ║
 * ║  MONETARY SAFETY: Price in cents (integer) to avoid float errors.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CreateProductCommand
{
    public function __construct(
        /** Tenant ID for multi-tenant isolation */
        public string $tenantId,

        /** Product SKU (Unique) */
        public string $sku,

        /** Product name */
        public string $name,

        /** Product description */
        public string $description,

        /** Price in cents (integer for precision) */
        public int $price,

        /** Initial stock quantity */
        public int $stock = 0,

        /** Category ID */
        public ?int $categoryId = null,

        /**
         * CRITICAL: Path to already-uploaded image (NOT UploadedFile binary).
         * The Controller uploads the file first, then passes the path here.
         */
        public ?string $imagePath = null,

        /** Product visibility */
        public bool $isVisible = true,
    ) {}
}
