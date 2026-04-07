<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\TenantProductRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ProductController extends Controller
{
    public function __construct(
        private readonly TenantProductRepository $repository,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantContext->getTenantId();
        $search = $request->string('search')->toString();
        $products = $this->repository->getDashboardList($tenantId, $search, 10);

        return Inertia::render('Tenant/Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): HttpResponse
    {
        return Inertia::location(route('filament.app.resources.products.index', [
            'tenant' => $this->tenantSlug(),
        ], false));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->redirectToOfficialProducts('La gestion de productos ya se administra desde el panel oficial en /app.');
    }

    public function edit(int $id): HttpResponse
    {
        return Inertia::location(route('filament.app.resources.products.index', [
            'tenant' => $this->tenantSlug(),
        ], false));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        return $this->redirectToOfficialProducts('La gestion de productos ya se administra desde el panel oficial en /app.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        return $this->redirectToOfficialProducts('La gestion de productos ya se administra desde el panel oficial en /app.');
    }

    public function toggleVisibility(Request $request, int $id): RedirectResponse
    {
        return $this->redirectToOfficialProducts('La gestion de productos ya se administra desde el panel oficial en /app.');
    }

    private function redirectToOfficialProducts(string $message): RedirectResponse
    {
        return redirect()
            ->to(route('filament.app.resources.products.index', ['tenant' => $this->tenantSlug()], false))
            ->with('error', $message);
    }

    private function tenantSlug(): string
    {
        return (string) ($this->tenantContext->getTenantData(['slug'])['slug'] ?? '');
    }
}
