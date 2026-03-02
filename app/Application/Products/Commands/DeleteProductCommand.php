<?php

declare(strict_types=1);

namespace App\Application\Products\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;

/**
 * DeleteProductCommand - Immutable Input Contract for Product Deletion
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SOFT DELETE: Product is marked deleted but preserved for restoration.  ║
 * ║  TENANT ISOLATED: Requires tenant ID for ownership verification.        ║
 * ║  IDEMPOTENT: Replaying a delete on an already-deleted product is safe.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class DeleteProductCommand implements IdempotentCommandInterface, \App\Application\Shared\Contracts\VersionedCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        /** Tenant ID for ownership verification */
        public string $tenantId,

        /** Product ID to delete */
        public int $productId,

        /** Idempotency Key (mandatory for deduplication) */
        public string $idempotencyKey,

        /** Schema version for upcasting support */
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
