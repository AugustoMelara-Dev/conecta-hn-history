<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Scopes\LocationScope;
use App\Scopes\PublicVisibilityScope;
use App\Traits\BelongsToTenant;
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
    use BelongsToTenant, HasFactory, SoftDeletes, \Spatie\MediaLibrary\InteractsWithMedia;

    /**
     * Attributes to automatically append to JSON/Array serialization.
     *
     * FRONTEND CONTRACT:
     * The React frontend expects 'image_url' in every product object.
     * Without this, catalog grids would be "blind" (no visual data).
     *
     * @var array<string>
     */
    protected $appends = [
        'image_url',
        'is_in_stock',
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
        'status',
        'moderation_note',
        'rejected_at',
    ];



    protected $casts = [
        'price' => 'decimal:2',
        'variants' => 'array',
        // Inventory
        'manage_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'allow_backorders' => 'boolean',
        'stock_status' => StockStatus::class,
        // Display flags
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'is_locked' => 'boolean',
        'rejected_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new LocationScope);
        static::addGlobalScope(new PublicVisibilityScope);
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10);

        $this->addMediaConversion('optimized')
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
        return $this->stock_status === StockStatus::IN_STOCK;
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
}
