<?php

namespace App\Models;

use App\Scopes\LocationScope;
use App\Scopes\PublicVisibilityScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use HasFactory, SoftDeletes, \Spatie\MediaLibrary\InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'city_id',
        'category_id',
        'menu_section_id',
        'name',
        'slug',
        'description',
        'price',
        'variants',
        'image_path',
        'blur_hash',
        'is_visible',
        'is_featured',
        'is_locked',
        'status',
        'moderation_note',
        'rejected_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'variants' => 'array',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function menuSection(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class);
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
     * Grupos de opciones del producto (variantes, extras)
     */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order');
    }
}
