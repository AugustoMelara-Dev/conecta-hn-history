<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WorkspaceRedirectController extends Controller
{
    public function __construct(
        private readonly CanonicalPanelUrlResolver $panelUrlResolver
    ) {}

    public function dashboard(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request);
    }

    public function products(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'products');
    }

    public function createProduct(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'products.create');
    }

    public function editProduct(Request $request, string $product): RedirectResponse
    {
        return $this->redirectToPanel($request, 'products.edit', ['product' => $product]);
    }

    public function orders(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'orders');
    }

    public function billing(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'billing');
    }

    public function settings(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'settings');
    }

    public function marketing(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'marketing');
    }

    public function upgrade(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'upgrade');
    }

    public function reports(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'reports');
    }

    public function landingPage(Request $request): RedirectResponse
    {
        return $this->redirectToPanel($request, 'settings');
    }

    private function redirectToPanel(Request $request, string $section = 'dashboard', array $parameters = []): RedirectResponse
    {
        return redirect()->to($this->panelUrlResolver->forUser($request->user(), $section, $parameters));
    }
}
