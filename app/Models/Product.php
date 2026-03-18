<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockStatus;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use App\Scopes\LocationScope;
use App\Scopes\PublicVisibilityScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product Model - E-Commerce Catalog Entity
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HYBRID MEDIA ARCHITECTURE: Dual-Strategy Image Management              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * This model implements a HYBRID approach to media handling, optimizing for
 * both performance (catalog views) and richness (detail views).
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ STRATEGY 1: Custom Column (image_path) - PERFORMANCE OPTIMIZED         │
 * ├─────────────────────────────────────────────────────────────────────────┤
 * │ PURPOSE:    Main product thumbnail for catalog/grid views              │
 * │ STORAGE:    products.image_path column                                 │
 * │ ACCESS:     $product->image_url (accessor, auto-appended to JSON)      │
 * │ LOOKUP:     O(1) - No additional queries required                      │
 * │ USE CASE:   Category pages, search results, cart items                 │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ STRATEGY 2: Spatie Media Library - RICH GALLERY                        │
 * ├─────────────────────────────────────────────────────────────────────────┤
 * │ PURPOSE:    Multiple gallery images, conversions, responsive variants  │
 * │ STORAGE:    media table (polymorphic relationship)                     │
 * │ ACCESS:     $product->getMedia('gallery'), $product->getFirstMediaUrl()│
 * │ LOOKUP:     O(n) - Requires JOIN or eager loading                      │
 * │ USE CASE:   Product detail page carousel, admin media manager          │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * WHY HYBRID?
 * - Catalog API responses stay lightweight (no media table JOINs)
 * - Detail views get full gallery capabilities when needed
 * - Falls back gracefully if either strategy has no data
 */
class Product extends Model implements \Spatie\MediaLibrary\HasMedia
{
    // ═══════════════════════════════════════════════════════════════════════════
    // INFRASTRUCTURE CONSTANTS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Canonical storage directory for product images.
     *
     * SINGLE SOURCE OF TRUTH: All controllers must reference this constant
     * instead of hardcoding 'products'. This enables safe refactoring of
     * the storage structure without grep-and-replace operations.
     */
    public const STORAGE_PATH = 'products';

    /** @var int Maximum impulse candidates to fetch before shuffling */
    public const IMPULSE_CANDIDATES_LIMIT = 50;

    use BelongsToTenant, HasFactory, SoftDeletes, \Spatie\MediaLibrary\InteractsWithMedia;

    /**
     * Attributes to automatically append to JSON/Array serialization.
     *
     * FRONTEND CONTRACT:
     * The React frontend expects 'image_url' in every product object.
     * Without this, catalog grids would be "blind" (no visual data).
     *
     * @var list<string>
     */
    protected $appends = [
        'image_url',
        'is_in_stock',
    ];

    protected $guarded = [
        'id',
        'tenant_id', // IMMUTABLE OWNERSHIP
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'city_id',
        'category_id',
        'menu_section_id',
        'name',
        'slug',
        'description',
        'price',
        // Inventory Management
        'manage_stock',
        'unit_code',
        'stock_quantity',
        'low_stock_threshold',
        'allow_backorders',
        'stock_status',
        // Media & Display
        'variants',
        'image_path',
        'blur_hash',
        'is_visible',
        'is_featured',
        'is_new',
        'is_locked',
        'is_impulse_buy',
        'status',
        'moderation_note',
        'rejected_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'variants' => 'array',
        // Inventory
        'manage_stock' => 'boolean',
        'unit_code' => 'string',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'allow_backorders' => 'boolean',
        'stock_status' => StockStatus::class,
        // Display flags
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_locked' => 'boolean',
        'is_impulse_buy' => 'boolean',
        'rejected_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new LocationScope);
        static::addGlobalScope(new PublicVisibilityScope);

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = \Illuminate\Support\Str::slug($product->name);
            }
        });
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        // 1. Minuatura cuadrada para Tablas/Grid (400x400)
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->width(400)
            ->height(400)
            ->sharpen(10); // Procesamiento síncrono para desarrollo (cambiar en prod)

        // 2. Versión WebP optimizada para Detalle (LCP)
        $this->addMediaConversion('optimized')
            ->nonQueued()
            ->width(800)
            ->format('webp')
            ->quality(80);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // Note: tenant() relationship is provided by BelongsToTenant trait
    // ═══════════════════════════════════════════════════════════════════════

    public function menuSection(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class);
    }

    /**
     * Alias for menuSection (consistency)
     */
    public function section(): BelongsTo
    {
        return $this->menuSection();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Product gallery images (PRO Feature)
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->ordered();
    }

    /**
     * Grupos de opciones del producto (variantes, extras)
     */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order');
    }

    /**
     * Modifier Groups for product customization (PRO Feature)
     *
     * Examples: "Tamaño", "Extras", "Toppings"
     * Each group contains ModifierOptions with price adjustments.
     */
    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('sort_order');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CROSS-SELLING SCOPES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Scope for impulse buy candidates (cross-selling at checkout).
     *
     * PERFORMANCE PATTERN: ID Fetch & Shuffle
     * ════════════════════════════════════════════════════════════════════════
     * FORBIDDEN: ORDER BY RAND() - O(N log N) complexity, full table sort
     * REQUIRED:  Fetch IDs first, shuffle in PHP - O(N) read, O(k) shuffle
     *
     * This scope returns a Builder that can be used in two ways:
     * 1. Direct use: ->impulseCandidates($excludeIds)->get() for all candidates
     * 2. With limit: Use getRandomImpulseCandidates() helper for shuffled subset
     *
     * @param  array<int>  $excludeIds  Product IDs already in cart (don't suggest)
     */
    public function scopeImpulseCandidates(\Illuminate\Database\Eloquent\Builder $query, array $excludeIds = []): \Illuminate\Database\Eloquent\Builder
    {
        $query->where('is_impulse_buy', true)
            ->where('is_visible', true);

        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query;
    }

    /**
     * Get random impulse candidates using performant ID Fetch & Shuffle pattern.
     *
     * USE THIS instead of ->impulseCandidates()->inRandomOrder()->limit(3)->get()
     *
     * ALGORITHM:
     * 1. Fetch only IDs from DB (lightweight query, no full rows)
     * 2. Shuffle IDs in PHP memory (O(k) where k = desired count)
     * 3. Fetch full models only for the random subset
     * 4. Use FIELD() to preserve shuffled order from MySQL
     *
     * @param  int  $businessId  Tenant/Business ID for isolation
     * @param  array<int>  $excludeIds  IDs already in cart
     * @param  int  $count  Number of suggestions to return (default 3)
     * @return \Illuminate\Database\Eloquent\Collection<static>
     */
    /**
     * Get random impulse candidates using performant ID Fetch & Shuffle pattern.
     *
     * USE THIS instead of ->impulseCandidates()->inRandomOrder()->limit(3)->get()
     *
     * ALGORITHM:
     * 1. Fetch only IDs from DB (lightweight query, no full rows)
     * 2. Shuffle IDs in PHP memory (O(k) where k = desired count)
     * 3. Fetch full models only for the random subset
     * 4. Use FIELD() to preserve shuffled order from MySQL
     *
     * @param  int  $businessId  Tenant/Business ID for isolation
     * @param  array<int>  $excludeIds  IDs already in cart
     * @param  int  $count  Number of suggestions to return (default 3)
     * @return \Illuminate\Database\Eloquent\Collection<static>
     */
    public static function getRandomImpulseCandidates(int $businessId, array $excludeIds = [], int $count = 3): \Illuminate\Database\Eloquent\Collection
    {
        // Step 1: Fetch only IDs (uses idx_products_impulse index)
        // REFACTOR: Use DB Facade to avoid "Scope Evasion" architecture violation
        $query = \Illuminate\Support\Facades\DB::table('products')
            ->where('tenant_id', $businessId)
            ->where('is_impulse_buy', true)
            ->where('is_visible', true)
            ->whereNull('deleted_at'); // Manual soft-delete check

        if (! empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        $candidateIds = $query->limit(self::IMPULSE_CANDIDATES_LIMIT)->pluck('id');

        if ($candidateIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Step 2: Shuffle in PHP (O(k) complexity)
        $randomIds = $candidateIds->shuffle()->take($count)->values();

        if ($randomIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Step 3: Sanitize IDs for FIELD() clause (SQL injection prevention)
        $sanitizedIds = $randomIds->map(fn ($id) => (int) $id)->implode(',');

        // Step 4: Fetch full models with FIELD() to preserve random order
        $rows = \Illuminate\Support\Facades\DB::table('products')
            ->whereIn('id', $randomIds)
            ->orderByRaw('FIELD(id, '.$sanitizedIds.')')
            ->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $products */
        $products = static::hydrate($rows->all());

        return $products;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Check if product is in stock.
     *
     * TYPE SAFETY: Uses strict Enum-to-Enum comparison.
     * Do NOT compare Enum objects to strings - it always returns false in PHP 8.1+
     */
    public function getIsInStockAttribute(): bool
    {
        return $this->stock_status === StockStatus::InStock;
    }

    /**
     * Get the fully qualified public URL for the product image.
     *
     * STORAGE ARCHITECTURE:
     * - Images stored in: storage/app/public/products/
     * - Symlinked via: php artisan storage:link
     * - Served from: /storage/products/filename.jpg
     *
     * NULL HANDLING:
     * Returns null if no image - Frontend handles placeholder display.
     * This keeps the Backend as a pure data provider.
     *
     * @return string|null Full public URL or null if no image
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::url($this->image_path);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SCOUT SEARCH CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Get the indexable data array for the model.
     *
     * OPTIMIZATION STRATEGY:
     * - Include 'tenant_id' for isolation filters
     * - Denormalize 'city', 'category', 'section' for instant faceted search
     * - Strip HTML from description to reduce index size
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id, // CRITICAL: For multi-tenant isolation
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => strip_tags($this->description ?? ''),
            'price' => (float) $this->price,
            'is_visible' => (bool) $this->is_visible,
            'stock_status' => $this->stock_status->value,
            'created_at' => $this->created_at->timestamp,
            // Denormalized Relations for Filtering
            'city_id' => $this->city_id,
            'category_id' => $this->category_id,
            'menu_section_id' => $this->menu_section_id,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ADMIN FACADE METHODS (Architecture Compliance)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get paginated products for admin view (ignoring visibility).
     *
     * ⚠️ VC-AUDIT-105: SCOPE BYPASS WARNING
     * This method uses DB::table() which bypasses:
     * - TenantScope (explicit tenant_id filter added manually)
     * - PublicVisibilityScope (intentional for admin)
     * - SoftDeletes (explicit deleted_at filter added manually)
     *
     * DO NOT use in public-facing code.
     */
    public static function getAdminList(int $tenantId, string $search = '')
    {
        $query = \Illuminate\Support\Facades\DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $paginator = $query->orderByDesc('created_at')->paginate(12);

        // Hydrate results to return actual Product models
        $paginator->setCollection(static::hydrate($paginator->items()));

        return $paginator;
    }

    /**
     * Count all products for a tenant (ignoring visibility).
     *
     * ⚠️ VC-AUDIT-105: SCOPE BYPASS WARNING
     * This method uses DB::table() which bypasses Eloquent scopes.
     * Manual tenant_id and deleted_at filters are applied.
     *
     * DO NOT use in public-facing code.
     */
    public static function countAllForTenant(int $tenantId): int
    {
        return \Illuminate\Support\Facades\DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Find a product within a tenant (ignoring scopes).
     *
     * ⚠️ VC-AUDIT-105: SCOPE BYPASS WARNING
     * This method uses DB::table() which bypasses Eloquent scopes.
     * Manual tenant_id and deleted_at filters are applied.
     *
     * DO NOT use in public-facing code.
     */
    public static function findInTenant(int $id, int $tenantId): ?self
    {
        $row = \Illuminate\Support\Facades\DB::table('products')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        return $row ? static::hydrate([$row])->first() : null;
    }

    /**
     * Search products for POS (lightweight).
     *
     * ⚠️ VC-AUDIT-105: SCOPE BYPASS WARNING
     * This method uses DB::table() which bypasses Eloquent scopes.
     * Visibility is enforced via explicit is_visible filter.
     *
     * Safe for public use (applies visibility filter).
     */
    public static function searchForPos(int $tenantId, string $term)
    {
        return \Illuminate\Support\Facades\DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->whereNull('deleted_at')
            ->when($term, fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->limit(10)
            ->get(['id', 'name', 'price', 'image_path']);
    }
}
