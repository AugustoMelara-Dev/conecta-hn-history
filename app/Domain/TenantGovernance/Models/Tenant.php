<?php

declare(strict_types=1);

/**
 * Tenant Model - TenantGovernance Domain (Phase 4: Emergency Migration)
 *
 * MIGRATION NOTE: Moved from App\Models\Tenant to resolve Fatal Error.
 * User.php and AppServiceProvider were already referencing this namespace.
 *
 * CRITICAL: This file MUST exist for the application to boot.
 */

namespace App\Domain\TenantGovernance\Models;

use App\Domain\Identity\Models\User;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Interaction;
use App\Models\Lead;
use App\Models\MenuSection;
use App\Models\OpeningHour;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\TenantVisit;
use App\Scopes\LocationScope;
use App\Traits\CanBeFavorited;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(\App\Observers\TenantObserver::class)]
class Tenant extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use CanBeFavorited, HasFactory, LogsActivity, SoftDeletes, \Spatie\MediaLibrary\InteractsWithMedia;

    // ═══════════════════════════════════════════════════════════════════════════
    // SECURITY: Mass Assignment Protection
    // ═══════════════════════════════════════════════════════════════════════════
    protected $fillable = [
        'name',
        'slug',
        'description',
        'address',
        'city_id',
        'category_id',
        'whatsapp_number',
        'whatsapp_message',
        'primary_color',
        'phone',
        'email',
        'website_url',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'simple_schedule',
        'brand_color',
        'slogan',
        'seo_title',
        'seo_description',
        'tags',
        'payment_methods',
        'delivery_enabled',
        'pickup_enabled',
        'minimum_order',
    ];

    protected $guarded = [
        'id',
        'user_id',
        'status',
        'is_active',
        'plan_type',
        'subscription_status',
        'subscription_ends_at',
        'plan_expires_at',
        'product_limit',
        'is_pro',
    ];

    protected $casts = [
        'hours_data' => 'array',
        'is_active' => 'boolean',
        'theme_config' => 'array',
        'social_links' => 'array',
        'tags' => 'array',
        'plan_expires_at' => 'datetime',
        'next_billing_date' => 'date',
        'subscription_ends_at' => 'datetime',
        'payment_methods' => 'array',
        'delivery_enabled' => 'boolean',
        'pickup_enabled' => 'boolean',
        'minimum_order' => 'decimal:2',
        'is_ai_enhanced' => 'boolean',
        'landing_page_data' => 'array',
    ];

    protected $appends = [
        'subscription_status_label',
        'plan_type_label',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * CRITICAL: Override required for custom Domain namespace.
     */
    protected static function newFactory()
    {
        return \App\Domain\TenantGovernance\Database\Factories\TenantFactory::new();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ASSET MANAGEMENT (Spatie Media Library)
    // ═══════════════════════════════════════════════════════════════════════════

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->useDisk('public');

        $this->addMediaCollection('cover')
            ->singleFile()
            ->useDisk('public');

        $this->addMediaCollection('hero')
            ->singleFile()
            ->useDisk('public');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SUBSCRIPTION ENGINE
    // ═══════════════════════════════════════════════════════════════════════════

    public function getIsProAttribute(): bool
    {
        if (! in_array($this->plan_type, ['pro', 'enterprise'])) {
            return false;
        }

        if (in_array($this->subscription_status, ['past_due', 'canceled'])) {
            return false;
        }

        if (! in_array($this->subscription_status, ['active', 'trial'])) {
            return false;
        }

        if ($this->subscription_ends_at === null) {
            return true;
        }

        return $this->subscription_ends_at->isFuture();
    }

    public function isSubscriptionActive(): bool
    {
        if ($this->subscription_status !== 'active') {
            return false;
        }

        if ($this->subscription_ends_at === null) {
            return true;
        }

        return $this->subscription_ends_at->isFuture();
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if ($this->subscription_ends_at === null) {
            return null;
        }

        return max(0, now()->diffInDays($this->subscription_ends_at, false));
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        $remaining = $this->days_until_expiry;

        if ($remaining === null) {
            return false;
        }

        return $remaining <= $days && $remaining > 0;
    }

    public function getSubscriptionStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Activo',
            'trial' => 'Período de Prueba',
            'past_due' => 'Vencido',
            'canceled' => 'Cancelado',
        ];

        return $labels[$this->subscription_status] ?? 'Desconocido';
    }

    public function getPlanTypeLabelAttribute(): string
    {
        $labels = [
            'semilla' => 'Plan Semilla',
            'pro' => 'Plan Pro',
            'enterprise' => 'Plan Enterprise',
        ];

        return $labels[$this->plan_type] ?? 'Desconocido';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QUERY SCOPES
    // ═══════════════════════════════════════════════════════════════════════════

    public function scopePublished($query)
    {
        return $query
            ->where('is_active', true)
            ->where('subscription_status', 'active');
    }

    public function scopeInPriorityOrder($query)
    {
        return $query->orderByRaw("FIELD(plan_type, 'enterprise', 'pro', 'semilla')");
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        $term = mb_substr(trim($term), 0, 100);

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('tags', 'like', "%{$term}%");
        });
    }

    public function scopeInCategory($query, ?string $categorySlug)
    {
        if (empty($categorySlug)) {
            return $query;
        }

        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    public function scopeInCity($query, ?string $citySlug)
    {
        if (empty($citySlug)) {
            return $query;
        }

        return $query->whereHas('city', function ($q) use ($citySlug) {
            $q->where('slug', $citySlug);
        });
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

    protected static function booted(): void
    {
        static::addGlobalScope(new LocationScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'status',
                'user_id',
                'is_pro',
                'is_active',
                'plan_expires_at',
                'plan_type',
                'subscription_status',
                'subscription_ends_at',
                'primary_color',
                'brand_color',
                'payment_methods',
                'delivery_enabled',
                'pickup_enabled',
                'minimum_order',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => "Negocio '{$this->name}' registrado",
                'updated' => "Negocio '{$this->name}' actualizado",
                'deleted' => "Negocio '{$this->name}' eliminado",
                default => "Negocio '{$this->name}' {$eventName}",
            })
            ->useLogName('negocios');
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Get the owner of the tenant.
     * UPDATED: Points to Domain\Identity namespace.
     */
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

    public function menuSections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function tenantVisits(): HasMany
    {
        return $this->hasMany(TenantVisit::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows')
            ->withTimestamps();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week');
    }

    public function getThemeConfigAttribute($value): array
    {
        $config = $value ? json_decode($value, true) : [];

        $defaults = [
            'primary_color' => '#000000',
            'radius' => '0.5rem',
            'font' => 'Inter',
        ];

        $theme = array_merge($defaults, $config ?? []);

        if (isset($theme['primary_color'])) {
            if (! preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $theme['primary_color'])) {
                $theme['primary_color'] = '#000000';
            }
        }

        return $theme;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FEATURE GATING
    // ═══════════════════════════════════════════════════════════════════════════

    public function getPublicMenuStructure(?int $productLimit = null): ?\Illuminate\Support\Collection
    {
        if (! $this->is_pro) {
            return null;
        }

        return $this->menuSections()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['activeProducts' => function ($query) use ($productLimit) {
                if ($productLimit) {
                    $query->take($productLimit);
                }
            }])
            ->withCount(['products' => fn ($q) => $q->where('is_visible', true)])
            ->get();
    }

    public function getPublicProducts(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->products()
            ->where('is_visible', true)
            ->orderBy('name');

        if (! $this->is_pro) {
            $query->take($limit);
        }

        return $query->get();
    }
}
