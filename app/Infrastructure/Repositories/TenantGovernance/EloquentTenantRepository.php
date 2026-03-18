<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Models\Tenant;
use Spatie\OpeningHours\OpeningHours;

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
        $model = Tenant::where('slug', $domain)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function getFeatured(int $limit = 12): array
    {
        return Tenant::query()
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'hero_image',
                'cover_path',
                'brand_color',
                'plan_type',
                'is_pro',
            ])
            ->published()
            ->inPriorityOrder()
            ->take($limit)
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'description' => $tenant->description,
                'hero_image' => $tenant->hero_image,
                'cover_path' => $tenant->cover_path,
                'brand_color' => $tenant->brand_color,
                'plan_type' => $tenant->plan_type instanceof TenantPlan ? $tenant->plan_type->value : (string) $tenant->plan_type,
                'is_pro' => (bool) $tenant->is_pro,
            ])
            ->all();
    }

    public function countActive(): int
    {
        return Tenant::query()
            ->published()
            ->count();
    }

    public function getOpeningHours(string $tenantId): OpeningHours
    {
        $tenant = Tenant::find($tenantId);

        return $tenant?->getOpeningHours() ?? OpeningHours::create([
            'monday' => ['09:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            'wednesday' => ['09:00-17:00'],
            'thursday' => ['09:00-17:00'],
            'friday' => ['09:00-17:00'],
        ]);
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
        $state = $tenant->toStateArray();

        // Use forceFill to bypass mass assignment protection for guarded fields
        $model->forceFill([
            'id' => $state['id'],
            'user_id' => $state['owner_id'] ?? null,
            'name' => $state['name'],
            'slug' => $state['domain'],
            'is_active' => $state['is_active'],
            'category_id' => $state['category_id'] ?? null,
            'city_id' => $state['city_id'] ?? null,
            'logo_path' => $state['logo_path'] ?? null,
            'description' => $state['description'] ?? null,
            'slogan' => $state['slogan'] ?? null,
            'whatsapp_number' => $state['whatsapp'] ?? null,
            'is_ai_enhanced' => $state['is_ai_enhanced'] ?? false,
            'is_pro' => $state['is_pro'] ?? false,
            'product_limit' => $state['product_limit'] ?? 10,
            'plan_type' => $state['plan_id'] ?? TenantPlan::SEMILLA->value,
            'subscription_status' => $state['subscription_status'] ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS,
            'subscription_ends_at' => $state['subscription_ends_at'] ?? null,
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
            'subscription_status' => (string) ($model->subscription_status ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS),
            'subscription_ends_at' => $model->subscription_ends_at?->toIso8601String(),
            'subscription_reference' => null,

            // Profile & Settings
            'owner_id' => $model->user_id,
            'category_id' => $model->category_id,
            'city_id' => $model->city_id,
            'logo_path' => $model->logo_path,
            'description' => $model->description,
            'slogan' => $model->slogan,
            'whatsapp' => $model->whatsapp_number,
            'is_ai_enhanced' => (bool) $model->is_ai_enhanced,
            'is_pro' => (bool) $model->is_pro,
            'product_limit' => (int) $model->product_limit,
        ]);
    }
}
