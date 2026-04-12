<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\Transformers\TenantMicrositeTransformer;
// Assumed Event exists or will be created
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Throwable;

/**
 * TenantMicrositeController - Clean Read-Only
 *
 * ARCHITECTURE:
 * - Pure Read: No side-effects (Event Driven Views).
 * - Logic-Free: Queries moved to Repository.
 * - Gatekeeper: Visibility rules in Service.
 */
final class TenantMicrositeController extends Controller
{
    public function __construct(
        private readonly TenantStorefrontRepository $repository,
        private readonly TenantMicrositeTransformer $transformer,
        private readonly PlanFeatureFlagService $planService,
        private readonly TenantVisibilityService $visibilityService
    ) {}

    public function show(Request $request, string $tenantSlug, \App\Domain\Shared\Services\TenantContext $tenantContext): Response
    {
        $tenantLookup = Tenant::query()
            ->withoutGlobalScopes()
            ->select(['id', 'slug'])
            ->where('slug', $tenantSlug)
            ->first();

        if (! $tenantLookup) {
            abort(404);
        }

        $this->hydrateTenantContext($tenantContext, (string) $tenantLookup->id);

        $tenant = $this->repository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404);
        }

        $previewMode = false;
        $previewReason = null;

        try {
            // 2. Gatekeeper Logic (Domain Service)
            $this->visibilityService->check($tenant);
        } catch (TenantNotFoundException) {
            $viewer = $request->user();
            $canPreviewUnpublishedTenant = $viewer !== null
                && ($viewer->isSuperAdmin() || (string) $viewer->id === (string) $tenant->user_id);

            if (! $canPreviewUnpublishedTenant) {
                abort(404);
            }

            $previewMode = true;
            $previewReason = $this->resolvePreviewReason($tenant);
        }

        // 3. Side-Effect Managment (Event Driven)
        // Fire and Forget. Listener handles increment logic.
        // Needs basic Event class.
        $this->dispatchMicrositeViewedSafely($tenant, $request);

        // 4. Transform & Respond
        $tenantData = $this->safeTransformTenant($tenant);
        $visibleProductsCount = count($tenantData['products'] ?? []);
        $totalProductsCount = $this->safeTotalProductsCount($tenant, $visibleProductsCount);
        $productLimit = $this->planService->getMaxProductLimit($tenant);

        // Preserve the transformed DTO contract, then paginate the in-memory collection.
        $search = (string) $request->input('search', '');
        $productsCollection = collect($tenantData['products'] ?? []);

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $productsCollection = $productsCollection
                ->filter(function (array $product) use ($needle): bool {
                    $name = mb_strtolower((string) ($product['name'] ?? ''));
                    $description = mb_strtolower((string) ($product['description'] ?? ''));

                    return str_contains($name, $needle) || str_contains($description, $needle);
                })
                ->values();
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 12;

        $paginatedProducts = new \Illuminate\Pagination\LengthAwarePaginator(
            $productsCollection->forPage($page, $perPage)->values(),
            $productsCollection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $isOwner = $request->user() && $request->user()->id === $tenant->user_id;

        return Inertia::render('Tenant/Show', [
            'tenantPlan' => $this->planService->determinePlan($tenant),
            'tenant' => $tenantData,
            'products' => $paginatedProducts,
            'filters' => [
                'search' => $search,
            ],
            'hasHiddenProducts' => $totalProductsCount > $visibleProductsCount,
            'totalProductsCount' => $totalProductsCount,
            'hiddenProductsCount' => max(0, $totalProductsCount - $visibleProductsCount),
            'productLimit' => $productLimit,
            'isOwner' => $isOwner,
            'isFollowing' => false,
            'followersCount' => 0,
            'isOpen' => $this->safeIsCurrentlyOpen($tenant),
            'openingHours' => $this->safeOpeningHours($tenant),
            'seoStructuredData' => $this->safeStructuredData($tenant),
            'canonicalUrl' => route('tenant.microsite.show', ['tenant' => $tenant->slug]),
            'previewMode' => $previewMode,
            'previewReason' => $previewReason,
        ]);
    }

    private function resolvePreviewReason(object $tenant): string
    {
        $status = (string) ($tenant->status ?? '');

        if ($status === 'pending') {
            return 'Este negocio sigue pendiente de aprobacion.';
        }

        if ($status === 'rejected') {
            return 'Este negocio fue rechazado y aun no esta publicado.';
        }

        if (! (bool) ($tenant->is_active ?? false)) {
            return 'Este negocio esta desactivado temporalmente.';
        }

        $subscriptionStatus = (string) ($tenant->subscription_status ?? '');

        if (! in_array($subscriptionStatus, ['active', 'trial'], true)) {
            return 'La suscripcion actual no permite visibilidad publica.';
        }

        return 'Este negocio todavia no esta visible para visitantes.';
    }

    private function hydrateTenantContext(\App\Domain\Shared\Services\TenantContext $tenantContext, string $tenantId): void
    {
        if ($tenantContext->isInitialized()) {
            try {
                if ((string) $tenantContext->id() !== $tenantId) {
                    $tenantContext->reset();
                }
            } catch (LogicException) {
                // Root context or inconsistent state from previous request in long-lived workers.
                $tenantContext->reset();
            }
        }

        $tenantContext->setTenant($tenantId);
    }

    private function dispatchMicrositeViewedSafely(object $tenant, Request $request): void
    {
        if (! class_exists(\App\Events\Analytics\MicrositeViewed::class)) {
            return;
        }

        try {
            $tenantEntity = \App\Domain\TenantGovernance\Entities\TenantEntity::fromStateArray([
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->slug,
                'is_active' => $tenant->is_active,
            ]);

            \App\Events\Analytics\MicrositeViewed::dispatch($tenantEntity, $request->ip(), $request->userAgent());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function safeTransformTenant(object $tenant): array
    {
        try {
            $transformed = $this->transformer->transform($tenant);

            return is_array($transformed) ? $transformed : [];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'description' => $tenant->description ?? null,
                'products' => [],
                'services' => [],
            ];
        }
    }

    private function safeTotalProductsCount(object $tenant, int $fallback): int
    {
        try {
            return (int) $tenant->products()->where('is_visible', true)->count();
        } catch (Throwable $exception) {
            report($exception);

            return max(0, $fallback);
        }
    }

    private function safeIsCurrentlyOpen(object $tenant): bool
    {
        try {
            return (bool) $this->transformer->isCurrentlyOpen($tenant);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function safeOpeningHours(object $tenant): array
    {
        try {
            $openingHours = $this->transformer->transformOpeningHours($tenant->openingHours);

            return is_array($openingHours) ? $openingHours : [];
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function safeStructuredData(object $tenant): array
    {
        try {
            $structuredData = $this->transformer->buildStructuredData($tenant);

            return is_array($structuredData) ? $structuredData : [];
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }
}
