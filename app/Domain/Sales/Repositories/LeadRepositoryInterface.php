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
     * @param int $tenantId
     * @param int|null $productId
     * @param string $source Conversion source (whatsapp_click, etc.)
     * @param string|null $message Initial message/context
     * @return LeadEntity Created lead domain entity
     */
    public function createFromInteraction(
        int $tenantId,
        ?int $productId,
        string $source,
        ?string $message = null
    ): LeadEntity;

    /**
     * Find lead by ID.
     *
     * @param int $id
     * @return LeadEntity|null
     */
    public function findById(int $id): ?LeadEntity;
}
