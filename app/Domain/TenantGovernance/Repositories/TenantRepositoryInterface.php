<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use Spatie\OpeningHours\OpeningHours;

/**
 * TenantRepositoryInterface - Diamond Standard Port
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONCURRENCY SAFE: Includes pessimistic locking method for ACID ops.    ║
 * ║  UUID COMPATIBLE: Uses string IDs throughout.                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface TenantRepositoryInterface
{
    /**
     * Find tenant by ID without locking.
     */
    public function findById(string $id): ?TenantEntity;

    /**
     * Find tenant by ID with pessimistic lock (SELECT ... FOR UPDATE).
     *
     * CRITICAL: Use this within a transaction when performing
     * read-modify-write operations to prevent race conditions.
     */
    public function findByIdForUpdate(string $id): ?TenantEntity;

    public function findBySlug(string $slug): ?TenantEntity;

    /**
     * Check existence efficiently without hydrating the entity.
     * O(1) operation.
     */
    public function existsBySlug(string $slug): bool;

    public function findByDomain(string $domain): ?TenantEntity;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFeatured(int $limit = 12): array;

    public function countActive(): int;

    public function getOpeningHours(string $tenantId): OpeningHours;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TenantEntity;

    public function save(TenantEntity $tenant): TenantEntity;

    public function delete(TenantEntity $tenant): void;
}
