<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Models\Tenant;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;

/**
 * EloquentTenantRepository - Infrastructure Adapter for Tenant Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL ROLE: Infrastructure Adapter (Hexagonal Architecture)    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This class is the ONLY place where Eloquent ORM is used for Tenant     ║
 * ║  operations. All static calls (::find, ::where, ::create) are           ║
 * ║  encapsulated here, keeping the Application Layer ORM-agnostic.         ║
 * ║                                                                          ║
 * ║  REPLACEABILITY:                                                         ║
 * ║  - For unit tests: Swap with InMemoryTenantRepository                   ║
 * ║  - For read replicas: Swap with ReadReplicaTenantRepository             ║
 * ║  - For caching: Wrap with CachingTenantRepositoryDecorator              ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentTenantRepository implements TenantRepositoryInterface
{
    /**
     * Find a tenant by its primary identifier.
     */
    public function findById(int $id): ?Tenant
    {
        return Tenant::find($id);
    }

    /**
     * Find a tenant by its unique slug.
     */
    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    /**
     * Find a tenant by its domain.
     */
    public function findByDomain(string $domain): ?Tenant
    {
        return Tenant::where('domain', $domain)->first();
    }

    /**
     * Create a new tenant entity.
     *
     * @param array<string, mixed> $attributes
     * @return Tenant
     */
    public function create(array $attributes): Tenant
    {
        return Tenant::create($attributes);
    }

    /**
     * Persist a tenant entity to the data store.
     *
     * DEFERRED EVENT DISPATCH:
     * After successful save, this method extracts any domain events
     * recorded by the entity and dispatches them via Laravel's event bus.
     * This ensures events are only fired when persistence succeeds.
     */
    public function save(Tenant $tenant): void
    {
        // Persist to database
        $tenant->save();

        // Release and dispatch domain events
        $events = $tenant->releaseEvents();

        foreach ($events as $event) {
            event($event);
        }
    }

    /**
     * Remove a tenant from the data store.
     */
    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }
}
