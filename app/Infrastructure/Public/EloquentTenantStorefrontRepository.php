<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

/**
 * EloquentTenantStorefrontRepository - Optimized Read Implementation
 */
final class EloquentTenantStorefrontRepository implements TenantStorefrontRepository
{
    public function __construct(
        private readonly PlanFeatureFlagService $planService
    ) {}

    public function findBySlug(string $slug): ?object
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->where('slug', $slug)
            ->first();

        if (! $tenant) {
            return null;
        }

        // Logic extracted from Controller
        $productLimit = $this->planService->getMaxProductLimit($tenant);
        $canShowModifiers = $this->planService->canShowModifiers($tenant);

        $relations = [];

        if (Schema::hasTable('opening_hours')) {
            $relations[] = 'openingHours';
        }

        if (Schema::hasTable('menu_sections')) {
            $relations['menuSections'] = fn ($q) => $q->where('is_active', true)->orderBy('name');
        }

        if (Schema::hasTable('products')) {
            $relations['products'] = function ($query) use ($productLimit) {
                $query->where('is_visible', true)
                    ->orderBy('name')
                    ->limit($productLimit);
            };
        } else {
            $tenant->setRelation('products', new EloquentCollection());
        }

        if (Schema::hasColumn('tenants', 'city_id')) {
            $relations[] = 'city:id,name,slug';
        }

        if (Schema::hasColumn('tenants', 'category_id')) {
            $relations[] = 'category:id,name,slug,icon';
        }

        if (
            $canShowModifiers
            && Schema::hasTable('products')
            && Schema::hasTable('modifier_groups')
            && Schema::hasTable('modifier_options')
        ) {
            $relations['products.modifierGroups'] = fn ($q) => $q
                ->where('is_active', true)
                ->orderBy('sort_order');
            $relations['products.modifierGroups.options'] = fn ($q) => $q
                ->where('is_available', true)
                ->orderBy('sort_order');
        }

        return $relations === [] ? $tenant : $tenant->load($relations);
    }
}
