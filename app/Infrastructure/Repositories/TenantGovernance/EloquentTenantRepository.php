<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Models\Tenant;

/**
 * EloquentTenantRepository - Infrastructure Adapter for Tenant Persistence
 *
 * Implements strict type conversion between Domain and Eloquent.
 */
final class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findById(string $id): ?TenantEntity
    {
        $model = Tenant::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdForUpdate(string $id): ?TenantEntity
    {
        $model = Tenant::lockForUpdate()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySlug(string $slug): ?TenantEntity
    {
        $model = Tenant::where('slug', $slug)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * Efficient O(1) existence check.
     */
    public function existsBySlug(string $slug): bool
    {
        return Tenant::where('slug', $slug)->exists();
    }

    public function findByDomain(string $domain): ?TenantEntity
    {
        $model = Tenant::where('domain', $domain)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TenantEntity
    {
        // Direct model creation for legacy/factory support, though ideally we use factories
        $model = Tenant::create($attributes);

        return $this->toEntity($model);
    }

    public function save(TenantEntity $tenant): TenantEntity
    {
        // Find existing or create new model instance
        $model = Tenant::find($tenant->id()) ?? new Tenant;

        // Use forceFill to bypass mass assignment protection for guarded fields
        $model->forceFill([
            'id' => $tenant->id(),
            'name' => $tenant->name(),
            'slug' => $tenant->domain(), // TenantEntity uses "domain" for what Eloquent calls "slug"
            'plan' => 'SEED', // Default plan for new registrations
            'is_active' => $tenant->isActive(),
        ]);

        $model->save();

        return $tenant;
    }

    public function delete(TenantEntity $tenant): void
    {
        if ($tenant->id) {
            Tenant::destroy($tenant->id);
        }
    }

    private function toEntity(Tenant $model): TenantEntity
    {
        // LIFTING TO DOMAIN
        // We rely on Eloquent Casting ($casts) for 'plan_type'.
        // However, for defense in depth, we verify or fallback safely.

        $plan = $model->plan_type;
        if (! $plan instanceof TenantPlan) {
            // Should not happen if Model casts are correct, but handling legacy strings:
            $plan = TenantPlan::from((string) ($plan ?? 'semilla'));
        }

        return new TenantEntity(
            id: $model->id,
            userId: $model->user_id,
            name: $model->name,
            slug: $model->slug,
            source: $model->source ?? 'SYSTEM', // FALLBACK
            planType: $plan,
            subscriptionStatus: $model->subscription_status ?? 'active',
            isActive: (bool) $model->is_active,
            createdAt: $model->created_at instanceof \Carbon\Carbon ? \DateTimeImmutable::createFromMutable($model->created_at) : new \DateTimeImmutable,
            updatedAt: $model->updated_at instanceof \Carbon\Carbon ? \DateTimeImmutable::createFromMutable($model->updated_at) : new \DateTimeImmutable,
        );
    }
}
