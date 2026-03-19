<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\Shared\Exceptions\StaleObjectException;
use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
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
            ->map(function (Tenant $tenant): array {
                $planType = $tenant->getAttribute('plan_type');

                return [
                    'id' => (string) $tenant->getKey(),
                    'name' => (string) $tenant->name,
                    'slug' => (string) $tenant->slug,
                    'description' => $this->nullableString($tenant->getAttribute('description')),
                    'hero_image' => $this->nullableString($tenant->getAttribute('hero_image')),
                    'cover_path' => $this->nullableString($tenant->getAttribute('cover_path')),
                    'brand_color' => $this->nullableString($tenant->getAttribute('brand_color')),
                    'plan_type' => $planType instanceof TenantPlan
                        ? $planType->value
                        : (string) ($planType ?? TenantPlan::SEMILLA->value),
                    'is_pro' => (bool) $tenant->is_pro,
                ];
            })
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
            'cover_path' => $state['cover_path'] ?? null,
            'phone' => $state['phone'] ?? null,
            'latitude' => $state['latitude'] ?? null,
            'longitude' => $state['longitude'] ?? null,
            'description' => $state['description'] ?? null,
            'about' => $state['about'] ?? null,
            'slogan' => $state['slogan'] ?? null,
            'whatsapp_number' => $state['whatsapp'] ?? null,
            'primary_color' => $state['primary_color'] ?? null,
            'website_url' => $state['website_url'] ?? null,
            'facebook_url' => $state['facebook_url'] ?? null,
            'instagram_url' => $state['instagram_url'] ?? null,
            'tiktok_url' => $state['tiktok_url'] ?? null,
            'landing_page_data' => $state['landing_page_data'] ?? null,
            'simple_schedule' => $state['simple_schedule'] ?? null,
            'is_ai_enhanced' => $state['is_ai_enhanced'] ?? false,
            'is_pro' => $state['is_pro'] ?? false,
            'product_limit' => $state['product_limit'] ?? 10,
            'plan_type' => $state['plan_id'] ?? TenantPlan::SEMILLA->value,
            'subscription_status' => $state['subscription_status'] ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS,
            'subscription_ends_at' => $state['subscription_ends_at'] ?? null,
            'version' => max(1, $tenant->version()),
        ]);

        $model->save();

        return $tenant;
    }

    public function updateOptimistically(TenantEntity $tenant): void
    {
        $state = $tenant->toStateArray();
        $affected = Tenant::query()
            ->whereKey($tenant->id())
            ->where('version', max(1, $tenant->version()))
            ->update([
                'user_id' => $state['owner_id'] ?? null,
                'name' => $state['name'],
                'slug' => $state['domain'],
                'is_active' => $state['is_active'],
                'category_id' => $state['category_id'] ?? null,
                'city_id' => $state['city_id'] ?? null,
                'logo_path' => $state['logo_path'] ?? null,
                'cover_path' => $state['cover_path'] ?? null,
                'phone' => $state['phone'] ?? null,
                'latitude' => $state['latitude'] ?? null,
                'longitude' => $state['longitude'] ?? null,
                'description' => $state['description'] ?? null,
                'about' => $state['about'] ?? null,
                'slogan' => $state['slogan'] ?? null,
                'whatsapp_number' => $state['whatsapp'] ?? null,
                'primary_color' => $state['primary_color'] ?? null,
                'website_url' => $state['website_url'] ?? null,
                'facebook_url' => $state['facebook_url'] ?? null,
                'instagram_url' => $state['instagram_url'] ?? null,
                'tiktok_url' => $state['tiktok_url'] ?? null,
                'landing_page_data' => $state['landing_page_data'] ?? null,
                'simple_schedule' => $state['simple_schedule'] ?? null,
                'is_ai_enhanced' => $state['is_ai_enhanced'] ?? false,
                'is_pro' => $state['is_pro'] ?? false,
                'product_limit' => $state['product_limit'] ?? 10,
                'plan_type' => $state['plan_id'] ?? TenantPlan::SEMILLA->value,
                'subscription_status' => $state['subscription_status'] ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS,
                'subscription_ends_at' => $state['subscription_ends_at'] ?? null,
                'updated_at' => now(),
                'version' => DB::raw('version + 1'),
            ]);

        if ($affected === 0) {
            throw new StaleObjectException("Tenant {$tenant->id()} was modified by another process.");
        }
    }

    public function delete(TenantEntity $tenant): void
    {
        $tenantId = $tenant->id();

        if ($tenantId !== '') {
            Tenant::destroy($tenantId);
        }
    }

    private function toEntity(Tenant $model): TenantEntity
    {
        // LIFTING TO DOMAIN
        // We rely on Eloquent Casting ($casts) for 'plan_type'.
        // However, for defense in depth, we verify or fallback safely.

        $planType = $model->getAttribute('plan_type');
        $plan = $planType instanceof TenantPlan
            ? $planType
            : TenantPlan::from((string) ($planType ?? TenantPlan::SEMILLA->value));

        return TenantEntity::fromStateArray([
            'id' => (string) $model->getKey(),
            'name' => (string) $model->name,
            'domain' => (string) $model->slug, // Mapping slug -> domain
            'is_active' => (bool) $model->is_active,
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String(),
            'plan_id' => $plan->value,
            'subscription_status' => (string) ($model->getAttribute('subscription_status') ?? Tenant::DEFAULT_SUBSCRIPTION_STATUS),
            'subscription_ends_at' => $model->subscription_ends_at?->toIso8601String(),
            'subscription_reference' => null,

            // Profile & Settings
            'owner_id' => $this->nullableString($model->getAttribute('user_id')),
            'category_id' => $this->nullableInt($model->getAttribute('category_id')),
            'city_id' => $this->nullableInt($model->getAttribute('city_id')),
            'logo_path' => $this->nullableString($model->getAttribute('logo_path')),
            'cover_path' => $this->nullableString($model->getAttribute('cover_path')),
            'description' => $this->nullableString($model->getAttribute('description')),
            'about' => $this->nullableString($model->getAttribute('about')),
            'slogan' => $this->nullableString($model->getAttribute('slogan')),
            'whatsapp' => $this->nullableString($model->getAttribute('whatsapp_number')),
            'phone' => $this->nullableString($model->getAttribute('phone')),
            'address' => null,
            'latitude' => $this->nullableString($model->getAttribute('latitude')),
            'longitude' => $this->nullableString($model->getAttribute('longitude')),
            'primary_color' => $this->nullableString($model->getAttribute('primary_color')),
            'website_url' => $this->nullableString($model->getAttribute('website_url')),
            'instagram_url' => $this->nullableString($model->getAttribute('instagram_url')),
            'facebook_url' => $this->nullableString($model->getAttribute('facebook_url')),
            'tiktok_url' => $this->nullableString($model->getAttribute('tiktok_url')),
            'landing_page_data' => $this->nullableArray($model->getAttribute('landing_page_data')),
            'simple_schedule' => $this->nullableArray($model->getAttribute('simple_schedule')),
            'is_ai_enhanced' => (bool) $model->getAttribute('is_ai_enhanced'),
            'is_pro' => (bool) $model->is_pro,
            'product_limit' => (int) ($model->getAttribute('product_limit') ?? 10),
            'version' => (int) ($model->getAttribute('version') ?? 1),
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value !== null ? (string) $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nullableArray(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }
}
