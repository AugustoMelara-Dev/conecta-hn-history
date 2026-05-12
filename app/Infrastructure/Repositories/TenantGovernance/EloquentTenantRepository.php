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

    /**
     * Find tenant by ID with a pessimistic lock (SELECT ... FOR UPDATE).
     * Use inside a DB transaction when performing read-modify-write operations.
     */
    public function findByIdForUpdate(string $id): ?TenantEntity
    {
        // Use whereKey + lockForUpdate; caller should wrap this in a transaction.
        $model = Tenant::whereKey($id)->lockForUpdate()->first();

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
            'user_id' => $tenant->ownerUserId(),
            'name' => $tenant->name(),
            'slug' => $tenant->domain(), // TenantEntity uses "domain" for what Eloquent calls "slug"
            'domain' => $tenant->domain(),
            'plan' => 'SEED', // Default plan for new registrations
            'plan_type' => $tenant->planType() ?? 'semilla',
            'product_limit' => $tenant->productLimit() ?? 50,
            'is_pro' => $tenant->isPro(),
            'logo_path' => $tenant->logoPath(),
            'category_id' => $tenant->categoryId(),
            'city_id' => $tenant->cityId(),
            'whatsapp_number' => $tenant->whatsapp(),
            'business_type' => $tenant->businessType()->value,
            'commercial_mode' => $tenant->commercialMode()->value,
            'rubro_key' => $tenant->rubroKey(),
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

        return TenantEntity::fromStateArray([
            'id' => $model->id,
            'name' => $model->name,
            'domain' => $model->slug, // Mapping slug -> domain
            'is_active' => (bool) $model->is_active,
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String(),
            'plan_id' => $plan->value,
            'plan_type' => $plan->value,
            'is_pro' => (bool) ($model->is_pro ?? false),
            'product_limit' => $model->product_limit,
            'logo_path' => $model->logo_path,
            'category_id' => $model->category_id,
            'city_id' => $model->city_id,
            'whatsapp_number' => $model->whatsapp_number,
            'business_type' => $model->business_type?->value ?? $model->business_type,
            'commercial_mode' => $model->commercial_mode?->value ?? $model->commercial_mode,
            'rubro_key' => $model->rubro_key ?? null,
            'user_id' => $model->user_id,
            'subscription_ends_at' => $model->subscription_ends_at?->toIso8601String(),
            'subscription_reference' => null,
            // 'user_id' is NOT present in Domain Entity? 
            // If it were, we'd add it here. For now, we omit to prevent crash.
        ]);
    }
}
