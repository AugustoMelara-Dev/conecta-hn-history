<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\Tenant;
use LogicException;

/**
 * EloquentTenantStorefrontRepository - Optimized Read Implementation
 */
final class EloquentTenantStorefrontRepository implements TenantStorefrontRepository
{
    public function __construct(
        private readonly PlanFeatureFlagService $planService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function findBySlug(string $slug): ?object
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->first();

        if (! $tenant) {
            return null;
        }

        $this->hydrateTenantContext((string) $tenant->id);

        // Logic extracted from Controller
        $productLimit = $this->planService->getMaxProductLimit($tenant);
        $canShowModifiers = $this->planService->canShowModifiers($tenant);

        $relations = [
            'city:id,name,slug',
            'category:id,name,slug,icon',
            'openingHours' => fn ($q) => $q->withoutGlobalScopes(),
            'menuSections' => fn ($q) => $q
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->withCount(['products' => fn ($query) => $query->where('is_visible', true)])
                ->orderBy('sort_order')
                ->orderBy('name'),
            'products' => function ($query) use ($productLimit) {
                $query->withoutGlobalScopes()
                    ->where('is_visible', true)
                    ->orderBy('name')
                    ->limit($productLimit);
            },
            'services' => fn ($q) => $q->withoutGlobalScopes()->where('is_active', true)->orderBy('name'),
            'serviceProviders' => fn ($q) => $q->withoutGlobalScopes()->where('is_active', true)->orderBy('name'),
        ];

        if ($canShowModifiers) {
            $relations['products.modifierGroups'] = fn ($q) => $q
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->orderBy('sort_order');
            $relations['products.modifierGroups.options'] = fn ($q) => $q
                ->withoutGlobalScopes()
                ->where('is_available', true)
                ->orderBy('sort_order');
        }

        return $tenant->load($relations);
    }

    private function hydrateTenantContext(string $tenantId): void
    {
        if ($this->tenantContext->isInitialized()) {
            try {
                if ((string) $this->tenantContext->id() !== $tenantId) {
                    $this->tenantContext->reset();
                }
            } catch (LogicException) {
                $this->tenantContext->reset();
            }
        }

        $this->tenantContext->setTenant($tenantId);
    }
}
