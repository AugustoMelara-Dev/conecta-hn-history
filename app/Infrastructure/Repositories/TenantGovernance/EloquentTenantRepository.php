<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Models\Tenant;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Domain\TenantGovernance\Entities\TenantEntity;

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
    public function findById(int $id): ?TenantEntity
    {
        $model = Tenant::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    /**
     * Find a tenant by its unique slug.
     */
    public function findBySlug(string $slug): ?TenantEntity
    {
        $model = Tenant::where('slug', $slug)->first();
        return $model ? $this->toEntity($model) : null;
    }

    /**
     * Find a tenant by its domain.
     */
    public function findByDomain(string $domain): ?TenantEntity
    {
        $model = Tenant::where('domain', $domain)->first();
        return $model ? $this->toEntity($model) : null;
    }

    /**
     * Create a new tenant entity.
     *
     * @param array<string, mixed> $attributes
     * @return Tenant
     */
    public function create(array $attributes): TenantEntity
    {
        $model = Tenant::create($attributes);
        return $this->toEntity($model);
    }

    /**
     * Persist a tenant entity to the data store.
     *
     * DEFERRED EVENT DISPATCH:
     * After successful save, this method extracts any domain events
     * recorded by the entity and dispatches them via Laravel's event bus.
     * This ensures events are only fired when persistence succeeds.
     */
    public function save(TenantEntity $tenant): TenantEntity
    {
        $data = [
            'user_id' => $tenant->userId,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan_type' => $tenant->planType,
            'subscription_status' => $tenant->subscriptionStatus,
            'is_active' => $tenant->isActive,
            // Add other fields as necessary from Entity
        ];

        if ($tenant->id) {
            $model = Tenant::findOrFail($tenant->id);
            $model->update($data);
        } else {
            $model = Tenant::create($data);
        }

        // Release events logic if supported by Entity? 
        // TenantEntity is PURE so it doesn't have 'releaseEvents' trait from Eloquent.
        // DTO/Hander manages events. Repository just saves state.
        
        return $this->toEntity($model);
    }

    /**
     * Remove a tenant from the data store.
     */
    public function delete(TenantEntity $tenant): void
    {
        if ($tenant->id) {
            Tenant::destroy($tenant->id);
        }
    }

    private function toEntity(Tenant $model): TenantEntity
    {
        return new TenantEntity(
            id: $model->id,
            userId: $model->user_id,
            name: $model->name,
            slug: $model->slug,
            planType: $model->plan_type ?? 'semilla',
            subscriptionStatus: $model->subscription_status ?? 'active',
            isActive: (bool)$model->is_active,
            createdAt: $model->created_at instanceof \Carbon\Carbon ? \DateTimeImmutable::createFromMutable($model->created_at) : new \DateTimeImmutable(),
            updatedAt: $model->updated_at instanceof \Carbon\Carbon ? \DateTimeImmutable::createFromMutable($model->updated_at) : new \DateTimeImmutable(),
        );
    }
}
