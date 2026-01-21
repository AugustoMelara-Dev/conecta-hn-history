<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\CouponRepository;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MarketingController - Hexagonal Compliant (DIP Remediation)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero App\Models imports.
 * - TenantContext for tenant resolution (no $request->user()->tenant).
 * - Validation extracted to FormRequests.
 */
final class MarketingController extends Controller
{
    public function __construct(
        private readonly CouponRepository $couponRepository,
        private readonly TenantContext $tenantContext
    ) {}

    public function index(): Response
    {
        $tenantId = $this->tenantContext->getTenantId();

        $coupons = $this->couponRepository->getDashboardList(
            tenantId: $tenantId,
            perPage: 10
        );

        return Inertia::render('Tenant/Marketing/Index', [
            'coupons' => $coupons,
            'stats' => [], // Placeholder: Stats should come from CouponRepository::getStats() if needed
            'tenant' => $this->tenantContext->getTenantData(['id', 'name', 'slug', 'is_pro']),
        ]);
    }

    /*
     * NOTE: Write operations (store, toggle) are commented out pending Action implementation.
     * They require CreateCouponAction and ToggleCouponAction to maintain architectural compliance.
     *
     * public function store(StoreCouponRequest $request, CreateCouponAction $action): RedirectResponse
     * {
     *     $action->execute($this->tenantContext->getTenantId(), $request->validated());
     *     return back()->with('success', 'Cupón creado.');
     * }
     *
     * public function toggle(int $couponId, ToggleCouponAction $action): RedirectResponse
     * {
     *     $action->execute($this->tenantContext->getTenantId(), $couponId);
     *     return back();
     * }
     */
}
