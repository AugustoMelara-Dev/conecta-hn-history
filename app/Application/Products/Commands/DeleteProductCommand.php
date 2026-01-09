<?php

declare(strict_types=1);

namespace App\Application\Products\Commands;

/**
 * DeleteProductCommand - Immutable Input Contract for Product Deletion
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SOFT DELETE: Product is marked deleted but preserved for restoration.  ║
 * ║  TENANT ISOLATED: Requires tenant ID for ownership verification.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class DeleteProductCommand
{
    public function __construct(
        /** Tenant ID for ownership verification */
        public int $tenantId,
        
        /** Product ID to delete */
        public int $productId,
    ) {}
}
