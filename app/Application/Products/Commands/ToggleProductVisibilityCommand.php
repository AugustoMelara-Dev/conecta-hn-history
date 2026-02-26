<?php

declare(strict_types=1);

namespace App\Application\Products\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;

/**
 * ToggleProductVisibilityCommand - Immutable Input Contract for Visibility Toggle
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDEMPOTENT: Toggles current state; replays are safe with same key.     ║
 * ║  TENANT ISOLATED: Requires tenant ID for ownership verification.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ToggleProductVisibilityCommand implements IdempotentCommandInterface
{
    public function __construct(
        /** Tenant ID for ownership verification */
        public string $tenantId,

        /** Product ID to toggle */
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
