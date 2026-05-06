<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Actions\Tenant\UpdateLandingPageAction;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        private readonly UpdateLandingPageAction $updateAction
    ) {}

    public function index(Request $request): Response
    {
        $tenantData = $this->tenantContext->getTenantData(['id', 'name', 'slug', 'is_pro', 'landing_page_data']);

        return Inertia::render('Tenant/LandingPage', [
            'tenant' => [
                'id' => $tenantData['id'] ?? null,
                'name' => $tenantData['name'] ?? null,
                'slug' => $tenantData['slug'] ?? null,
            ],
            'landingPageData' => $tenantData['landing_page_data'] ?? ['sections' => []],
            'isPro' => (bool) ($tenantData['is_pro'] ?? false),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sections' => ['present', 'array'],
        ]);

        $tenantId = $this->tenantContext->getTenantId();

        // DELEGATED: All mutation logic in Action
        try {
            $this->updateAction->execute($tenantId, $validated['sections']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['landing_page' => $e->getMessage()]);
        }

        return back()->with('success', 'Landing page guardada.');
    }
}
