<?php

namespace App\Models;

use App\Scopes\LocationScope;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(\App\Observers\TenantObserver::class)]
class Tenant extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use HasFactory, \Spatie\MediaLibrary\InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'category_id',
        'logo_path',
        'banner_path',
        'blur_hash',
        'primary_color',
        'is_pro',
        'status',
        'plan_expires_at',
        'whatsapp_number',
        'whatsapp_template',
        'address',
        'city',
        'city_id',
        'description',
        'user_id',
        'hours_data',
        'theme_config',
        'product_limit',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'hours_data' => \App\Casts\OpeningHoursCast::class,
        'is_pro' => 'boolean',
        'theme_config' => 'array',
    ];

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

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new LocationScope);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the users who follow this tenant (Social Loyalty).
     */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows')
            ->withTimestamps();
    }

    /**
     * Get the campaigns created by this tenant.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Get theme configuration with validated hex colors and defaults.
     *
     * SECURITY: Validates hex color to prevent CSS injection
     */
    public function getThemeConfigAttribute($value): array
    {
        $config = $value ? json_decode($value, true) : [];

        // Default theme
        $defaults = [
            'primary_color' => '#000000',
            'radius' => '0.5rem',
            'font' => 'Inter',
        ];

        $theme = array_merge($defaults, $config ?? []);

        // CRITICAL: Validate hex color to prevent injection
        if (isset($theme['primary_color'])) {
            if (! preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $theme['primary_color'])) {
                // Invalid hex, use default
                $theme['primary_color'] = '#000000';
            }
        }

        return $theme;
    }
}
