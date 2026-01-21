<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Interfaces;

use App\Domain\TenantGovernance\Entities\TenantEntity;

/**
 * TenantRepositoryInterface - Honest Contract
 *
 * - Uses Strict UUIDs (No legacy integers).
 * - Defines explicit persistence and retrieval contracts.
 */
interface TenantRepositoryInterface
{
    public function save(TenantEntity $tenant): void;

    public function findById(string $tenantId): ?TenantEntity;

    public function existsByDomain(string $domain): bool;
}
