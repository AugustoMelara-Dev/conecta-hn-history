<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Products\Commands\CreateProductCommand;
use App\Application\Products\Commands\DeleteProductCommand;
use App\Application\Products\Commands\ToggleProductVisibilityCommand;
use App\Application\Products\Commands\UpdateProductCommand;
use App\Application\Products\Handlers\CreateProductHandler;
use App\Application\Products\Handlers\DeleteProductHandler;
use App\Application\Products\Handlers\ToggleProductVisibilityHandler;
use App\Application\Products\Handlers\UpdateProductHandler;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\TenantProductRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    public function create(): Response
    {
        return Inertia::render('Tenant/Products/Create');
    }

    public function store(StoreProductRequest $request, CreateProductHandler $handler): RedirectResponse
    {
        $validated = $request->validated();
        $tenantUuid = $this->tenantContext->getTenantUuid();
        $imagePath = $request->file('image')?->store("tenants/{$tenantUuid}/products", 'public');
        $sku = $this->resolveSku($validated, $tenantUuid);

        $handler->handle(new CreateProductCommand(
            tenantId: $tenantUuid,
            sku: $sku,
            name: (string) $validated['name'],
            description: (string) ($validated['description'] ?? ''),
            price: (int) round((float) $validated['price'] * 100),
            idempotencyKey: $this->resolveIdempotencyKey($request, [
                'tenant_id' => $tenantUuid,
                'sku' => $validated['sku'] ?? null,
                'name' => $validated['name'],
            ]),
            stock: 0,
            categoryId: null,
            imagePath: $imagePath,
            isVisible: (bool) ($validated['is_visible'] ?? true),
        ));

        return redirect()->route('tenant.products.index')->with('success', 'Producto creado.');
    }

    public function edit(int $id): Response
    {
        return Inertia::render('Tenant/Products/Edit', [
            'productId' => $id,
        ]);
    }

    public function update(UpdateProductRequest $request, UpdateProductHandler $handler, int $id): RedirectResponse
    {
        $validated = $request->validated();
        $tenantUuid = $this->tenantContext->getTenantUuid();
        $imagePath = $request->file('image')?->store("tenants/{$tenantUuid}/products", 'public');

        $handler->handle(new UpdateProductCommand(
            tenantId: $tenantUuid,
            productId: $id,
            name: (string) $validated['name'],
            description: (string) ($validated['description'] ?? ''),
            price: (int) round((float) $validated['price'] * 100),
            idempotencyKey: $this->resolveIdempotencyKey($request, [
                'tenant_id' => $tenantUuid,
                'product_id' => $id,
                'name' => $validated['name'],
            ]),
            sku: isset($validated['sku']) ? (string) $validated['sku'] : null,
            stock: 0,
            categoryId: null,
            imagePath: $imagePath,
            isVisible: (bool) ($validated['is_visible'] ?? true),
        ));

        return back()->with('success', 'Producto actualizado.');
    }

    public function destroy(Request $request, DeleteProductHandler $handler, int $id): RedirectResponse
    {
        $tenantUuid = $this->tenantContext->getTenantUuid();

        $handler->handle(new DeleteProductCommand(
            tenantId: $tenantUuid,
            productId: $id,
            idempotencyKey: $this->resolveIdempotencyKey($request, [
                'tenant_id' => $tenantUuid,
                'product_id' => $id,
                'action' => 'delete',
            ]),
        ));

        return back()->with('success', 'Producto eliminado.');
    }

    public function toggleVisibility(Request $request, ToggleProductVisibilityHandler $handler, int $id): RedirectResponse
    {
        $tenantUuid = $this->tenantContext->getTenantUuid();

        $handler->handle(new ToggleProductVisibilityCommand(
            tenantId: $tenantUuid,
            productId: $id,
            idempotencyKey: $this->resolveIdempotencyKey($request, [
                'tenant_id' => $tenantUuid,
                'product_id' => $id,
                'action' => 'toggle_visibility',
            ]),
        ));

        return back()->with('success', 'Visibilidad actualizada.');
    }

    private function resolveIdempotencyKey(Request $request, array $payload): string
    {
        $headerKey = $request->header('Idempotency-Key');
        if (! is_string($headerKey) || $headerKey === '') {
            $headerKey = $request->header('X-Idempotency-Key');
        }

        if (is_string($headerKey) && $headerKey !== '') {
            return $headerKey;
        }

        return hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveSku(array $validated, string $tenantUuid): string
    {
        $sku = isset($validated['sku']) ? trim((string) $validated['sku']) : '';
        if ($sku !== '') {
            return $sku;
        }

        $slug = strtoupper((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $validated['name']));

        return substr($slug, 0, 8).'-'.substr(hash('xxh128', $tenantUuid.(string) $validated['name']), 0, 12);
    }
}
