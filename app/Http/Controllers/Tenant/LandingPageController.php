<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Tenant\Commands\UpdateLandingPageCommand;
use App\Application\Tenant\Handlers\UpdateLandingPageHandler;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LandingPageController - PRO Landing Page Builder (Hexagonal Compliant)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║  - Zero App\Models imports (DIP compliant)                               ║
 * ║  - Uses TenantContext for tenant resolution                              ║
 * ║  - Delegates mutations to UpdateLandingPageAction                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LandingPageController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly UpdateLandingPageHandler $updateHandler
    ) {}

    public function index(Request $request): Response
    {
        $tenantColumns = ['id', 'name', 'slug'];

        if (Schema::hasColumn('tenants', 'is_pro')) {
            $tenantColumns[] = 'is_pro';
        }

        if (Schema::hasColumn('tenants', 'plan_type')) {
            $tenantColumns[] = 'plan_type';
        }

        if (Schema::hasColumn('tenants', 'plan')) {
            $tenantColumns[] = 'plan';
        }

        if (Schema::hasColumn('tenants', 'landing_page_data')) {
            $tenantColumns[] = 'landing_page_data';
        }

        $tenantData = $this->tenantContext->getTenantData($tenantColumns);
        $planValue = $tenantData['plan_type'] ?? $tenantData['plan'] ?? null;

        if ($planValue instanceof \BackedEnum) {
            $planValue = $planValue->value;
        }

        return Inertia::render('Tenant/LandingPage', [
            'tenant' => [
                'id' => $tenantData['id'] ?? null,
                'name' => $tenantData['name'] ?? null,
                'slug' => $tenantData['slug'] ?? null,
            ],
            'landingPageData' => $tenantData['landing_page_data'] ?? ['sections' => []],
            'isPro' => (bool) ($tenantData['is_pro'] ?? false) || in_array($planValue, ['pro', 'enterprise'], true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sections' => ['present', 'array'],
        ]);

        $tenantData = $this->tenantContext->getTenantData(['id']);
        $tenantId = $tenantData['id'] ?? $this->tenantContext->getTenantId();

        // DELEGATED: All mutation logic in Action
        try {
            $this->updateHandler->handle(new UpdateLandingPageCommand($tenantId, $validated['sections']));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['landing_page' => $e->getMessage()]);
        }

        return back()->with('success', 'Landing page guardada.');
    }
}
