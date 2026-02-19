<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Repositories;

use App\Domain\Analytics\Entities\InteractionEntity;

/**
 * InteractionRepositoryInterface - Pure Domain Port for Interaction Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: Returns domain entities, not Eloquent models.                    ║
 * ║  VISITOR-AWARE: Deduplication respects unique visitor identity.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface InteractionRepositoryInterface
{
    /**
     * Check if a recent interaction exists (deduplication).
     *
     * CRITICAL: Segmented by visitorId to allow different visitors
     * to record the same interaction type in the same timeframe.
     *
     * @param  string  $visitorId  Unique visitor identifier (cookie/fingerprint)
     * @param  string  $type  Interaction type
     * @param  int  $withinMinutes  Time window in minutes
     * @return bool True if duplicate exists for THIS visitor
     */
    public function hasRecentInteraction(
        string $tenantId,
        string $visitorId,
        string $type,
        int $withinMinutes = 5
    ): bool;

    /**
     * Create a new interaction record.
     *
     * @return InteractionEntity Created interaction entity
     */
    public function create(
        string $tenantId,
        string $visitorId,
        string $type,
        ?string $productId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null,
        ?string $url = null,
        array $metadata = []
    ): InteractionEntity;
}
