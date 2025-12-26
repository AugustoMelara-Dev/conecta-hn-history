<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\TenantCacheManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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
                    'image_url' => $product->image_url,
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
            'image_url' => $product->image_url,
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
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  SAFE-CREATION PROTOCOL: Compensating Transaction Pattern               ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * ARCHITECTURE:
     * 1. CONSTRUCT: Upload file first (safe - can be deleted)
     * 2. TRANSACT: Create DB record with try/catch
     * 3. COMPENSATE: On DB failure, delete uploaded file to prevent storage rot
     *
     * GUARANTEE: Zero orphaned files on database failure.
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

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: STATE TRACKING (for compensation on failure)
        // ═══════════════════════════════════════════════════════════════════════
        $uploadedImagePath = null;

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: CONSTRUCTIVE (Upload - reversible operation)
        // ═══════════════════════════════════════════════════════════════════════
        // TENANT ISOLATION: Files stored in tenants/{id}/products for GDPR compliance
        if ($request->hasFile('image')) {
            $storagePath = "tenants/{$tenant->id}/" . Product::STORAGE_PATH;
            $uploadedImagePath = $request->file('image')->store($storagePath, 'public');
            $validated['image_path'] = $uploadedImagePath;
        }

        // Remove control flags
        unset($validated['image'], $validated['remove_image']);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: TRANSACTIONAL (DB Create with compensation on failure)
        // ═══════════════════════════════════════════════════════════════════════
        try {
            $product = $tenant->products()->create($validated);
        } catch (\Exception $e) {
            // COMPENSATING TRANSACTION: Rollback filesystem on DB failure
            if ($uploadedImagePath) {
                Storage::disk('public')->delete($uploadedImagePath);
            }
            throw $e; // Re-throw for proper HTTP error response
        }

        // NOTE: Cache invalidation handled by ProductObserver (SRP)

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
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  SAFE-WRITE PROTOCOL: Constructive → Transactional → Destructive        ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * ARCHITECTURE:
     * 1. PLAN: Determine what files need deletion/upload (no I/O yet)
     * 2. CONSTRUCT: Upload new file if needed (reversible)
     * 3. TRANSACT: Persist to database (atomic)
     * 4. DESTRUCT: Delete old files ONLY after successful commit
     *
     * COMPENSATION:
     * If database fails, any newly uploaded file is deleted to prevent storage leaks.
     *
     * CACHE INVALIDATION:
     * Handled by ProductObserver (ShouldHandleEventsAfterCommit) - NOT here.
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
        // PHASE 1: STATE INITIALIZATION (Planning - No I/O)
        // ═══════════════════════════════════════════════════════════════════════
        $oldImageToDelete = null;   // File to delete AFTER successful commit
        $newImageUploaded = null;   // New file path (for rollback on DB failure)

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: LOGIC CONSOLIDATION (I/O Planning)
        // ═══════════════════════════════════════════════════════════════════════
        // Determine if old image needs removal (explicit removal OR replacement)
        $explicitRemoval = $request->boolean('remove_image');
        $hasNewUpload = $request->hasFile('image');

        if (($explicitRemoval || $hasNewUpload) && $product->image_path) {
            // Mark for deletion AFTER database commits
            $oldImageToDelete = $product->image_path;
        }

        // If explicit removal requested, inject null into validated array
        if ($explicitRemoval && ! $hasNewUpload) {
            $validated['image_path'] = null;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: CONSTRUCTIVE (Upload new file - reversible)
        // ═══════════════════════════════════════════════════════════════════════
        // TENANT ISOLATION: Files stored in tenants/{id}/products for GDPR compliance
        if ($hasNewUpload) {
            $storagePath = "tenants/{$tenant->id}/" . Product::STORAGE_PATH;
            $newImageUploaded = $request->file('image')->store($storagePath, 'public');
            $validated['image_path'] = $newImageUploaded;
        }

        // Remove control flags from validated data
        unset($validated['image'], $validated['remove_image']);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 4: TRANSACTIONAL (Persist to DB with compensation on failure)
        // ═══════════════════════════════════════════════════════════════════════
        try {
            $product->update($validated);
        } catch (\Exception $e) {
            // COMPENSATION: Rollback filesystem changes on DB failure
            if ($newImageUploaded) {
                Storage::disk('public')->delete($newImageUploaded);
            }
            throw $e; // Re-throw to let exception handler respond
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 5: DESTRUCTIVE (Delete old file ONLY after successful commit)
        // ═══════════════════════════════════════════════════════════════════════
        // This is the ONLY safe place to delete - DB has already committed.
        if ($oldImageToDelete) {
            Storage::disk('public')->delete($oldImageToDelete);
        }

        // NOTE: Cache invalidation handled by ProductObserver (SRP)
        // DO NOT call $this->cacheManager->rotateVersion() here

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

        // NOTE: Cache invalidation handled by ProductObserver (SRP)

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente.',
        ]);
    }
}

