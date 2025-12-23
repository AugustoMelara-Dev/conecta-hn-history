<?php

namespace App\Models;

use App\Scopes\LocationScope;
use App\Scopes\PublicVisibilityScope;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use BelongsToTenant, HasFactory, SoftDeletes, \Spatie\MediaLibrary\InteractsWithMedia;

    protected $fillable = [
        'city_id',
        'category_id',
        'menu_section_id',
        'name',
        'slug',
        'description',
        'price',
        'stock_status',
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

    /**
     * SECURITY: Immutable ownership fields.
     * tenant_id is set automatically by BelongsToTenant trait on creation.
     */
    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'price' => 'decimal:2',
        'variants' => 'array',
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
     * Check if product is in stock
     */
    public function getIsInStockAttribute(): bool
    {
        return $this->stock_status === 'in_stock';
    }
}
