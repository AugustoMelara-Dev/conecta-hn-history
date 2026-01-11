<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Domain\TenantGovernance\Entities\TenantEntity;

/**
 * TenantRepositoryInterface - Domain Port for Tenant Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL PURITY: NO ELOQUENT DEPENDENCIES                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Updated to use strict TenantEntity.                                  ║
 * ║  - Enforces Re-hydration pattern (save returns TenantEntity).           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface TenantRepositoryInterface
{
    public function findById(int $id): ?TenantEntity;

    public function findBySlug(string $slug): ?TenantEntity;

    public function findByDomain(string $domain): ?TenantEntity;

    /**
     * @param array<string, mixed> $attributes
     * @return TenantEntity
     */
    public function create(array $attributes): TenantEntity;

    public function save(TenantEntity $tenant): TenantEntity;

    public function delete(TenantEntity $tenant): void;
}
