<?php

declare(strict_types=1);

namespace App\Domain\Sales\Repositories;

use App\Domain\Sales\Entities\LeadEntity;

/**
 * LeadRepositoryInterface - Pure Domain Port for Lead Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: Returns domain entities, not Eloquent models.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface LeadRepositoryInterface
{
    /**
     * Create a lead from an interaction conversion.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  int|null  $productId  Related product ID, when available
     * @param  string  $source  Conversion source (whatsapp_click, etc.)
     * @param  string|null  $message  Initial message/context
     * @param  string|null  $sessionId  Visitor/session identifier for attribution
     * @return LeadEntity Created lead domain entity
     */
    public function createFromInteraction(
        string $tenantId,
        ?int $productId,
        string $source,
        ?string $message = null,
        ?string $sessionId = null,
        array $attribution = [],
    ): LeadEntity;

    /**
     * Find lead by ID.
     */
    public function findById(int $id): ?LeadEntity;
}
