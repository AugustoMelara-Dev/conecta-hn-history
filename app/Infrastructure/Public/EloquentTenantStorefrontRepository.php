<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Models\Tenant;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;

/**
 * EloquentTenantStorefrontRepository - Optimized Read Implementation
 */
final class EloquentTenantStorefrontRepository implements TenantStorefrontRepository
{
    public function __construct(
        private readonly PlanFeatureFlagService $planService
    ) {}

    public function findBySlug(string $slug): ?Tenant
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant) {
            return null;
        }

        // Logic extracted from Controller
        $productLimit = $this->planService->getMaxProductLimit($tenant);
        $canShowModifiers = $this->planService->canShowModifiers($tenant);

        $relations = [
            'city:id,name,slug',
            'category:id,name,slug,icon',
            'openingHours',
            'menuSections' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'products' => function ($query) use ($productLimit) {
                $query->where('is_visible', true)
                    ->orderBy('name')
                    ->limit($productLimit);
            },
        ];

        if ($canShowModifiers) {
            $relations['products.modifierGroups'] = fn ($q) => $q
                ->where('is_active', true)
                ->orderBy('sort_order');
            $relations['products.modifierGroups.options'] = fn ($q) => $q
                ->where('is_available', true)
                ->orderBy('sort_order');
        }

        return $tenant->load($relations);
    }
}
