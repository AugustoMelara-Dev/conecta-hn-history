<?php

declare(strict_types=1);

namespace App\Application\Products\Commands;

/**
 * ToggleProductVisibilityCommand - Immutable Input Contract for Visibility Toggle
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDEMPOTENT: Toggles current state, no target state specified.          ║
 * ║  TENANT ISOLATED: Requires tenant ID for ownership verification.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ToggleProductVisibilityCommand
{
    public function __construct(
        /** Tenant ID for ownership verification */
        public string $tenantId,

        /** Product ID to toggle */
        public int $productId,
    ) {}
}
