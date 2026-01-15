<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Domain\TenantGovernance\Entities\TenantEntity;

/**
 * TenantRepositoryInterface - Diamond Standard Port
 */
interface TenantRepositoryInterface
{
    public function findById(int $id): ?TenantEntity;

    public function findBySlug(string $slug): ?TenantEntity;

    /**
     * Check existence efficiently without hydrating the entity.
     * O(1) operation.
     */
    public function existsBySlug(string $slug): bool;

    public function findByDomain(string $domain): ?TenantEntity;

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): TenantEntity;

    public function save(TenantEntity $tenant): TenantEntity;

    public function delete(TenantEntity $tenant): void;
}
