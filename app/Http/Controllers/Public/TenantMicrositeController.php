<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Tenant\Transformers\TenantMicrositeTransformer;
use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
// Assumed Event exists or will be created
use App\Http\Controllers\Controller;
use App\Infrastructure\Public\EloquentTenantMicrositeReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        private readonly TenantVisibilityService $visibilityService,
        private readonly EloquentTenantMicrositeReader $micrositeReader,
    ) {}

    public function show(Request $request): Response
    {
        if ($this->isDirectoryAppHost($request)) {
            return app(DirectoryController::class)->index($request);
        }

        // 1. Resolve Tenant from Container (subdomain) or route model (/t/{slug} bridge).
        $routeTenant = $request->route('tenant');
        $tenant = is_object($routeTenant)
            ? $routeTenant
            : (app()->bound('current_tenant') ? app('current_tenant') : null);

        if (! $tenant && is_string($routeTenant) && $routeTenant !== '') {
            $tenant = $this->repository->findBySlug($routeTenant);
        }

        if (! $tenant) {
            abort(404, 'Business not found');
        }

        $tenant = $this->repository->findBySlug($tenant->slug) ?? $tenant;

        // 2. Gatekeeper Logic (Domain Service)
        $this->visibilityService->check($tenant);

        // 3. Side-Effect Managment (Event Driven)
        // Fire and Forget. Listener handles increment logic.
        // Needs basic Event class.
        if (class_exists(\App\Events\Analytics\MicrositeViewed::class)) {
            \App\Events\Analytics\MicrositeViewed::dispatch($tenant, $request->ip(), $request->userAgent());
        }

        // 4. Transform & Respond
        $totalProductsCount = $this->micrositeReader->visibleProductsCount($tenant);
        $tenantData = $this->transformer->transform($tenant);
        $visibleProductsCount = count($tenantData['products']);

        $isOwner = $request->user() && $request->user()->id === $tenant->user_id;
        $crmMicrosite = $this->micrositeReader->crmData($tenant, (bool) $isOwner);
        $hiddenProductsCount = max(0, $totalProductsCount - $visibleProductsCount);

        return Inertia::render('Tenant/Show', [
            'tenant' => $tenantData,
            'products' => $tenantData['products'],
            'storefrontCapabilities' => [
                'checkout' => (bool) ($tenantData['features']['can_use_cart'] ?? false),
                'modifiers' => (bool) ($tenantData['features']['can_show_modifiers'] ?? false),
                'reviews' => (bool) ($tenantData['features']['can_show_reviews'] ?? false),
            ],
            'hasHiddenProducts' => $isOwner && $hiddenProductsCount > 0,
            'totalProductsCount' => $isOwner ? $totalProductsCount : $visibleProductsCount,
            'hiddenProductsCount' => $isOwner ? $hiddenProductsCount : 0,
            'isOwner' => $isOwner,
            'isFollowing' => false,
            'followersCount' => 0,
            'isOpen' => $this->transformer->isCurrentlyOpen($tenant),
            'openingHours' => $this->transformer->transformOpeningHours($tenant->relationLoaded('openingHours') ? $tenant->openingHours : collect()),
            'seoStructuredData' => $this->transformer->buildStructuredData($tenant),
            'canonicalUrl' => route('tenant.microsite.show', $tenant),
            'crmMicrosite' => $crmMicrosite,
        ]);
    }

    private function isDirectoryAppHost(Request $request): bool
    {
        $directoryHost = config('conecta.directory_app_host');

        if ((string) $request->route('tenant') === 'app') {
            return true;
        }

        return is_string($directoryHost)
            && $directoryHost !== ''
            && strcasecmp($request->getHost(), $directoryHost) === 0;
    }

}
