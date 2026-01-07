<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ProductController - Tenant Product CRUD
 *
 * SECURITY: Zero-Trust Tenant Isolation
 * All queries scoped to $request->user()->tenant->products()
 * NO global Product::find() allowed (IDOR vulnerability).
 */
final class ProductController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════
    // INDEX - Paginated Product List
    // ═══════════════════════════════════════════════════════════════════════
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant($request);
        $search = $request->string('search')->trim()->toString();

        // Database-agnostic search (MySQL LIKE / Postgres ILIKE)
        $products = $tenant->products()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('sku', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        // Transform to frontend contract
        $products->through(fn (Product $product) => $this->transformProduct($product));

        // Calculate quota limits
        $currentCount = $tenant->products()->count();
        $limit = $tenant->is_pro ? 999 : 10;
        $percentage = $limit > 0 ? ($currentCount / $limit) * 100 : 0;

        return Inertia::render('Tenant/Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search],
            'limits' => [
                'current_count' => $currentCount,
                'limit' => $limit,
                'percentage' => round($percentage, 1),
                'can_create' => $currentCount < $limit,
            ],
            'tenant' => $tenant->only('id', 'name', 'slug'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CREATE - New Product Form
    // ═══════════════════════════════════════════════════════════════════════
    public function create(Request $request): Response
    {
        $tenant = $this->getTenant($request);

        // Check quota before allowing creation
        $currentCount = $tenant->products()->count();
        $limit = $tenant->is_pro ? 999 : 10;

        if ($currentCount >= $limit) {
            return redirect()
                ->route('tenant.products.index')
                ->with('error', 'Has alcanzado el límite de productos de tu plan.');
        }

        return Inertia::render('Tenant/Products/Form', [
            'product' => null,
            'sections' => $this->getMenuSections($tenant),
            'stockOptions' => $this->getStockOptions(),
            'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STORE - Create New Product
    // ═══════════════════════════════════════════════════════════════════════
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $tenant = $this->getTenant($request);
        $validated = $request->validated();

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->storeImage($request->file('image'), $tenant);
        }

        // Create product (tenant_id auto-set via BelongsToTenant trait)
        $tenant->products()->create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'is_visible' => $validated['is_visible'] ?? true,
            'stock_status' => $validated['stock_status'] ?? 'in_stock',
            'menu_section_id' => $validated['menu_section_id'] ?? null,
            'is_featured' => $validated['is_featured'] ?? false,
            'is_new' => $validated['is_new'] ?? false,
            'image_path' => $imagePath,
        ]);

        return redirect()
            ->route('tenant.products.index')
            ->with('success', 'Producto creado exitosamente.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EDIT - Edit Product Form
    // ═══════════════════════════════════════════════════════════════════════
    public function edit(Request $request, int $productId): Response
    {
        $tenant = $this->getTenant($request);
        $product = $this->findProductOrFail($tenant, $productId);

        return Inertia::render('Tenant/Products/Form', [
            'product' => $this->transformProduct($product),
            'sections' => $this->getMenuSections($tenant),
            'stockOptions' => $this->getStockOptions(),
            'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPDATE - Update Existing Product
    // ═══════════════════════════════════════════════════════════════════════
    public function update(UpdateProductRequest $request, int $productId): RedirectResponse
    {
        $tenant = $this->getTenant($request);
        $product = $this->findProductOrFail($tenant, $productId);
        $validated = $request->validated();

        // Handle image update (with orphan file prevention)
        if ($request->hasFile('image')) {
            // Delete old image first
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $validated['image_path'] = $this->storeImage($request->file('image'), $tenant);
        }

        // Update product
        $product->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'description' => $validated['description'] ?? $product->description,
            'sku' => $validated['sku'] ?? $product->sku,
            'is_visible' => $validated['is_visible'] ?? $product->is_visible,
            'stock_status' => $validated['stock_status'] ?? $product->stock_status,
            'menu_section_id' => $validated['menu_section_id'] ?? $product->menu_section_id,
            'is_featured' => $validated['is_featured'] ?? $product->is_featured,
            'is_new' => $validated['is_new'] ?? $product->is_new,
            'image_path' => $validated['image_path'] ?? $product->image_path,
        ]);

        return redirect()
            ->route('tenant.products.index')
            ->with('success', 'Producto actualizado exitosamente.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DESTROY - Delete Product
    // ═══════════════════════════════════════════════════════════════════════
    public function destroy(Request $request, int $productId): RedirectResponse
    {
        $tenant = $this->getTenant($request);
        $product = $this->findProductOrFail($tenant, $productId);

        // Delete image file
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        // Soft delete (uses SoftDeletes trait)
        $product->delete();

        return redirect()
            ->route('tenant.products.index')
            ->with('success', 'Producto eliminado.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TOGGLE VISIBILITY - Quick Action
    // ═══════════════════════════════════════════════════════════════════════
    public function toggleVisibility(Request $request, int $productId): RedirectResponse
    {
        $tenant = $this->getTenant($request);
        $product = $this->findProductOrFail($tenant, $productId);

        $product->update(['is_visible' => ! $product->is_visible]);

        return back()->with('success', $product->is_visible ? 'Producto visible.' : 'Producto oculto.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get tenant from authenticated user (Zero-Trust).
     */
    private function getTenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        abort_unless($tenant, 403, 'Tenant not found for authenticated user.');

        return $tenant;
    }

    /**
     * Find product within tenant scope (IDOR prevention).
     */
    private function findProductOrFail(Tenant $tenant, int $productId): Product
    {
        return $tenant->products()->findOrFail($productId);
    }

    /**
     * Transform product to frontend contract.
     */
    private function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'description' => $product->description,
            'is_visible' => (bool) $product->is_visible,
            'is_featured' => (bool) $product->is_featured,
            'is_new' => (bool) $product->is_new,
            'stock_status' => $product->stock_status,
            'menu_section_id' => $product->menu_section_id,
            'image_url' => $product->image_url, // Accessor handles null
            'created_at' => $product->created_at?->toIso8601String(),
        ];
    }

    /**
     * Store image in tenant-scoped directory.
     */
    private function storeImage($file, Tenant $tenant): string
    {
        $directory = "tenants/{$tenant->id}/products";

        return $file->store($directory, 'public');
    }

    /**
     * Get menu sections for dropdown.
     */
    private function getMenuSections(Tenant $tenant): array
    {
        return $tenant->menuSections()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * Get stock status options.
     */
    private function getStockOptions(): array
    {
        return [
            'in_stock' => 'Disponible',
            'low_stock' => 'Pocas unidades',
            'out_of_stock' => 'Agotado',
        ];
    }
}
