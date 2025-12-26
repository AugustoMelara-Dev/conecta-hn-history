<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\TenantCacheManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * ProductController - Catalog Distribution System
 *
 * ARCHITECTURE: Generational Caching with Version Rotation
 *
 * This controller implements the Cache-Aside (Lazy Loading) pattern using
 * VERSIONED cache keys instead of Cache::tags() for driver-agnostic operation.
 *
 * CACHE KEY STRUCTURE:
 *   tenant_{tenantId}_v{version}_{context}
 *   Example: tenant_1_v1735150000_products_page_1_per_20
 *
 * INVALIDATION:
 * When a product is modified, ProductObserver rotates the tenant version,
 * making ALL old cache keys unreachable. O(1) complexity.
 *
 * COMPATIBILITY: Works with ALL cache drivers (file, database, redis, memcached).
 */
class ProductController extends Controller
{
    /**
     * Cache TTL for product catalog (60 minutes).
     */
    protected const CACHE_TTL_MINUTES = 60;

    /**
     * The tenant cache manager instance.
     */
    protected TenantCacheManager $cacheManager;

    /**
     * Create a new controller instance.
     */
    public function __construct(TenantCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Get published products for a tenant (public catalog).
     *
     * GET /api/products?tenant_id=X&page=1&per_page=20
     *
     * GENERATIONAL CACHING:
     * Key format: tenant_{id}_v{version}_products_page_{N}_per_{X}
     * Version rotation invalidates ALL pages atomically.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

        // ═══════════════════════════════════════════════════════════════════════
        // VALIDATION: Require tenant context
        // ═══════════════════════════════════════════════════════════════════════
        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_id es requerido',
            ], 400);
        }

        // Verify tenant exists and is active
        $tenant = Tenant::published()->find($tenantId);

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'error' => 'Negocio no encontrado',
            ], 404);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GENERATIONAL CACHING: Versioned key for atomic invalidation
        // ═══════════════════════════════════════════════════════════════════════
        $context = "products_page_{$page}_per_{$perPage}";
        $cacheKey = $this->cacheManager->makeKey((int) $tenantId, $context);
        $ttl = now()->addMinutes(self::CACHE_TTL_MINUTES);

        $data = Cache::remember($cacheKey, $ttl, function () use ($tenantId, $page, $perPage) {
            return $this->fetchProductsFromDatabase((int) $tenantId, $page, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => $data['products'],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $data['total'],
                'last_page' => $data['last_page'],
            ],
        ]);
    }

    /**
     * Fetch products from database (cache MISS path).
     */
    protected function fetchProductsFromDatabase(int $tenantId, int $page, int $perPage): array
    {
        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        $total = $query->count();

        $products = $query
            ->select([
                'id',
                'name',
                'description',
                'price',
                'compare_at_price',
                'image_path',
                'image_blurhash',
                'is_featured',
                'menu_section_id',
            ])
            ->with(['menuSection:id,name,sort_order'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'formatted_price' => 'L. ' . number_format($product->price, 2),
                    'compare_at_price' => $product->compare_at_price,
                    'has_discount' => $product->compare_at_price && $product->compare_at_price > $product->price,
                    'image_url' => $product->image_path ? asset('storage/' . $product->image_path) : null,
                    'image_blurhash' => $product->image_blurhash,
                    'is_featured' => $product->is_featured,
                    'section' => $product->menuSection ? [
                        'id' => $product->menuSection->id,
                        'name' => $product->menuSection->name,
                    ] : null,
                ];
            });

        return [
            'products' => $products,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get a single product (public view).
     *
     * GET /api/products/{id}?tenant_id=X
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_id es requerido',
            ], 400);
        }

        $context = "product_{$id}";
        $cacheKey = $this->cacheManager->makeKey((int) $tenantId, $context);
        $ttl = now()->addMinutes(self::CACHE_TTL_MINUTES);

        $product = Cache::remember($cacheKey, $ttl, fn() => $this->fetchSingleProduct((int) $tenantId, $id));

        if (! $product) {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Fetch single product from database.
     */
    protected function fetchSingleProduct(int $tenantId, int $id): ?array
    {
        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('id', $id)
            ->with(['menuSection:id,name', 'optionGroups.options'])
            ->first();

        if (! $product) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'formatted_price' => 'L. ' . number_format($product->price, 2),
            'compare_at_price' => $product->compare_at_price,
            'has_discount' => $product->compare_at_price && $product->compare_at_price > $product->price,
            'image_url' => $product->image_path ? asset('storage/' . $product->image_path) : null,
            'image_blurhash' => $product->image_blurhash,
            'is_featured' => $product->is_featured,
            'section' => $product->menuSection ? [
                'id' => $product->menuSection->id,
                'name' => $product->menuSection->name,
            ] : null,
            'option_groups' => $product->optionGroups->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'is_required' => $group->is_required,
                'options' => $group->options->map(fn($opt) => [
                    'id' => $opt->id,
                    'name' => $opt->name,
                    'price' => $opt->price,
                ]),
            ]),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // WRITE OPERATIONS (Admin/Tenant Actions)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Store a new product.
     *
     * POST /api/products
     *
     * BINARY INGESTION:
     * - Handles multipart/form-data with image file
     * - Stores image in products/ directory
     * - Saves relative path to image_path column
     */
    public function store(\App\Http\Requests\StoreProductRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene un negocio asociado.',
            ], 403);
        }

        $validated = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        // Remove 'image' key (we use 'image_path')
        unset($validated['image']);

        // Create product
        $product = $tenant->products()->create($validated);

        // Invalidate tenant cache
        $this->cacheManager->rotateVersion($tenant->id);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado exitosamente.',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image_url' => $product->image_url,
            ],
        ], 201);
    }

    /**
     * Update an existing product.
     *
     * PATCH /api/products/{product}
     *
     * GARBAGE COLLECTION:
     * When a new image is uploaded, the old image is deleted from disk
     * to prevent storage rot (orphaned files).
     *
     * IMAGE NULLIFICATION:
     * The remove_image boolean flag allows explicit image removal without replacement.
     */
    public function update(\App\Http\Requests\StoreProductRequest $request, Product $product): JsonResponse
    {
        $tenant = $request->user()->tenant;

        // Verify ownership
        if (! $tenant || $product->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 403);
        }

        $validated = $request->validated();

        // ═══════════════════════════════════════════════════════════════════════
        // PIPELINE STEP 1: EXPLICIT IMAGE REMOVAL (Nullification Signal)
        // ═══════════════════════════════════════════════════════════════════════
        // Process remove_image FIRST to clear the slate.
        // ARRAY INJECTION PATTERN: We inject values into $validated, NOT the model.
        // This ensures Eloquent's update() generates the correct SQL query.
        if ($request->boolean('remove_image') && $product->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image_path);
            // CRITICAL: Inject null into array, NOT the model object
            // $product->image_path = null would NOT persist (Eloquent ignores dirty attrs not in array)
            $validated['image_path'] = null;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PIPELINE STEP 2: IMAGE UPLOAD (Independent Operation)
        // ═══════════════════════════════════════════════════════════════════════
        // This is a SEPARATE if block (NOT elseif) to support "Replace" operations.
        //
        // SEQUENTIAL BEHAVIOR:
        // - "Remove Only": Step 1 runs, Step 2 skipped → image_path = null
        // - "Upload Only": Step 1 skipped, Step 2 runs → image_path = new file
        // - "Replace":     Step 1 clears old, Step 2 uploads new → image_path = new file
        if ($request->hasFile('image')) {
            // Garbage collection: delete any existing file (may still exist if Step 1 didn't run)
            if ($product->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image_path);
            }

            // Store new image
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        // Remove handled keys from validated
        unset($validated['image'], $validated['remove_image']);

        // Update product
        $product->update($validated);

        // Invalidate tenant cache
        $this->cacheManager->rotateVersion($tenant->id);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado exitosamente.',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image_url' => $product->image_url,
            ],
        ]);
    }

    /**
     * Delete a product (Soft Delete).
     *
     * DELETE /api/products/{product}
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  SOFT-DELETE ALIGNMENT: Filesystem preserved for restoration           ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * LIFECYCLE SYNCHRONIZATION:
     * Since Product uses SoftDeletes trait, we PRESERVE the image file.
     * This ensures that if the product is restored via $product->restore(),
     * the image_path still points to a valid file (no "ghost references").
     *
     * PHYSICAL DELETION:
     * Image files should only be deleted during Force Delete operations,
     * which requires explicit implementation if/when that feature is added.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $tenant = $request->user()->tenant;

        // Verify ownership
        if (! $tenant || $product->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 403);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // SOFT DELETE ONLY - NO FILESYSTEM INTERACTION
        // ═══════════════════════════════════════════════════════════════════════
        // Image file is PRESERVED to allow restoration without broken references.
        // Physical file cleanup should occur during Force Delete or scheduled GC.
        $product->delete();

        // Invalidate tenant cache
        $this->cacheManager->rotateVersion($tenant->id);

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente.',
        ]);
    }
}

