<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\Tenant;

/**
 * TenantRepositoryInterface - Domain Port for Tenant Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Dependency Inversion Principle (DIP)          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This interface defines the CONTRACT for tenant persistence without      ║
 * ║  revealing the underlying storage technology. The Application Layer     ║
 * ║  depends on this abstraction, NOT on Eloquent or any ORM.               ║
 * ║                                                                          ║
 * ║  BENEFITS:                                                               ║
 * ║  1. Unit Testing: Replace with InMemoryTenantRepository                 ║
 * ║  2. Flexibility: Swap MySQL for PostgreSQL/MongoDB without changes      ║
 * ║  3. Clean Architecture: Domain remains ORM-agnostic                     ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   // In Job's handle() method:
 *   public function handle(TenantRepositoryInterface $repository): void
 *   {
 *       $tenant = $repository->findById($this->tenantId);
 *   }
 */
interface TenantRepositoryInterface
{
    /**
     * Find a tenant by its primary identifier.
     *
     * @param  int  $id  The tenant's database ID
     * @return Tenant|null The tenant entity, or null if not found
     */
    public function findById(int $id): ?Tenant;

    /**
     * Find a tenant by its unique slug.
     *
     * @param  string  $slug  The tenant's URL-friendly identifier
     * @return Tenant|null The tenant entity, or null if not found
     */
    public function findBySlug(string $slug): ?Tenant;

    /**
     * Find a tenant by its domain.
     *
     * @param  string  $domain
     * @return Tenant|null
     */
    public function findByDomain(string $domain): ?Tenant;

    /**
     * Create a new tenant entity.
     *
     * @param array<string, mixed> $attributes
     * @return Tenant
     */
    public function create(array $attributes): Tenant;

    /**
     * Persist a tenant entity to the data store.
     *
     * @param  Tenant  $tenant  The tenant to save
     */
    public function save(Tenant $tenant): void;

    /**
     * Remove a tenant from the data store.
     *
     * @param  Tenant  $tenant  The tenant to delete
     */
    public function delete(Tenant $tenant): void;
}
